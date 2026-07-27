#!/usr/bin/env bash
# Smoke test for the Support Ticket Management API.
# Usage:  bash smoke-test.sh [base_url]
# Default base_url: http://127.0.0.1:8000/api/v1

B="${1:-http://127.0.0.1:8000/api/v1}"
J=(-H "Accept: application/json" -H "Content-Type: application/json")
PASS=0; FAIL=0

c()  { printf '\033[%sm%s\033[0m\n' "$1" "$2"; }
hdr(){ echo; c "1;36" "── $* ──"; }

# check <expected_code> <description> <curl args...>
check() {
  local want="$1" desc="$2"; shift 2
  local out code body
  out=$(curl -s -w $'\n%{http_code}' "$@")
  code=$(tail -n1 <<<"$out"); body=$(sed '$d' <<<"$out")
  if [ "$code" = "$want" ]; then
    PASS=$((PASS+1)); c "0;32" "  PASS [$code] $desc"
  else
    FAIL=$((FAIL+1)); c "0;31" "  FAIL [got $code, want $want] $desc"
    echo "        $body"
  fi
  LAST_BODY="$body"
}

jget() { php -r '$d=json_decode(stream_get_contents(STDIN),true); $k=explode(".",$argv[1]); foreach($k as $x){ $d = $d[$x] ?? null; } echo is_scalar($d)?$d:"";' "$1" <<<"$LAST_BODY"; }

login() {
  LAST_BODY=$(curl -s "${J[@]}" -X POST "$B/login" -d "{\"email\":\"$1\",\"password\":\"password\"}")
  jget "data.token"
}

hdr "1. AUTHENTICATION"
check 200 "login as user"            "${J[@]}" -X POST "$B/login" -d '{"email":"user@support.test","password":"password"}'
UT=$(jget "data.token")
check 401 "login with wrong password" "${J[@]}" -X POST "$B/login" -d '{"email":"user@support.test","password":"nope"}'
check 422 "register missing fields"   "${J[@]}" -X POST "$B/register" -d '{"email":"bad"}'
check 200 "GET /me with token"        "${J[@]}" -H "Authorization: Bearer $UT" "$B/me"
check 401 "GET /tickets without token" "${J[@]}" "$B/tickets"

hdr "2. TICKET CREATION + 3. AUTO ASSIGNMENT"
declare -a NUMS AGENTS
for i in 1 2 3 4; do
  check 201 "create ticket $i" "${J[@]}" -H "Authorization: Bearer $UT" -X POST "$B/tickets" \
    -d "{\"title\":\"Issue $i\",\"description\":\"desc $i\",\"priority\":\"high\"}"
  NUMS[$i]=$(jget "data.ticket_number"); AGENTS[$i]=$(jget "data.assigned_agent.name")
  ST=$(jget "data.status")
  echo "        ${NUMS[$i]}  status=$ST  agent=${AGENTS[$i]}"
  [ "$i" = 1 ] && T1=$(jget "data.id")
  [ "$i" = 2 ] && T2=$(jget "data.id")
done
check 422 "create with invalid priority" "${J[@]}" -H "Authorization: Bearer $UT" -X POST "$B/tickets" \
  -d '{"title":"x","description":"y","priority":"urgent"}'

echo
if [ "${AGENTS[1]}" != "${AGENTS[2]}" ] && [ "${AGENTS[2]}" != "${AGENTS[3]}" ] && [ "${AGENTS[1]}" = "${AGENTS[4]}" ]; then
  PASS=$((PASS+1)); c "0;32" "  PASS load-balanced round robin, 4th wrapped back to ${AGENTS[4]}"
else
  FAIL=$((FAIL+1)); c "0;31" "  FAIL assignment order: ${AGENTS[1]} ${AGENTS[2]} ${AGENTS[3]} ${AGENTS[4]}"
fi

hdr "4. STATUS WORKFLOW"
AT=$(login john@support.test)
PT=$(login priya@support.test)
check 422 "open -> resolved rejected"      "${J[@]}" -H "Authorization: Bearer $AT" -X PATCH "$B/tickets/$T1/status" -d '{"status":"resolved"}'
check 200 "open -> in_progress allowed"    "${J[@]}" -H "Authorization: Bearer $AT" -X PATCH "$B/tickets/$T1/status" -d '{"status":"in_progress"}'
check 200 "in_progress -> resolved allowed" "${J[@]}" -H "Authorization: Bearer $AT" -X PATCH "$B/tickets/$T1/status" -d '{"status":"resolved"}'
R=$(jget "data.resolved_at")
if [ -n "$R" ]; then PASS=$((PASS+1)); c "0;32" "  PASS resolved_at stamped ($R)"
else FAIL=$((FAIL+1)); c "0;31" "  FAIL resolved_at is null"; fi
check 422 "resolved -> in_progress rejected" "${J[@]}" -H "Authorization: Bearer $AT" -X PATCH "$B/tickets/$T1/status" -d '{"status":"in_progress"}'
check 422 "invalid status value"             "${J[@]}" -H "Authorization: Bearer $AT" -X PATCH "$B/tickets/$T1/status" -d '{"status":"closed"}'

hdr "AUTHORIZATION"
check 403 "agent updating another agent's ticket" "${J[@]}" -H "Authorization: Bearer $PT" -X PATCH "$B/tickets/$T1/status" -d '{"status":"in_progress"}'
check 403 "user updating a status"                "${J[@]}" -H "Authorization: Bearer $UT" -X PATCH "$B/tickets/$T2/status" -d '{"status":"in_progress"}'
check 403 "agent creating a ticket"               "${J[@]}" -H "Authorization: Bearer $AT" -X POST "$B/tickets" -d '{"title":"x","description":"y","priority":"low"}'
check 404 "non-existent ticket"                   "${J[@]}" -H "Authorization: Bearer $UT" "$B/tickets/99999"

hdr "5. LISTING, FILTERING, PAGINATION"
check 200 "user sees own tickets" "${J[@]}" -H "Authorization: Bearer $UT" "$B/tickets"
echo "        user total: $(jget 'meta.total')"
check 200 "agent sees only assigned" "${J[@]}" -H "Authorization: Bearer $AT" "$B/tickets"
echo "        agent total: $(jget 'meta.total')"
check 200 "filter status=open&priority=high" "${J[@]}" -H "Authorization: Bearer $UT" "$B/tickets?status=open&priority=high"
echo "        matched: $(jget 'meta.total')"
check 200 "sort=oldest"   "${J[@]}" -H "Authorization: Bearer $UT" "$B/tickets?sort=oldest"
check 200 "per_page=2"    "${J[@]}" -H "Authorization: Bearer $UT" "$B/tickets?per_page=2"
echo "        page $(jget 'meta.current_page') of $(jget 'meta.last_page')"
check 422 "invalid filter value" "${J[@]}" -H "Authorization: Bearer $UT" "$B/tickets?status=bogus"

hdr "AUTH TEARDOWN"
check 200 "logout"                   "${J[@]}" -H "Authorization: Bearer $UT" -X POST "$B/logout"
check 401 "token revoked after logout" "${J[@]}" -H "Authorization: Bearer $UT" "$B/tickets"

echo
c "1;36" "════════════════════════════════════"
c "0;32" "  PASSED: $PASS"
[ "$FAIL" -gt 0 ] && c "0;31" "  FAILED: $FAIL" || c "0;32" "  FAILED: 0"
c "1;36" "════════════════════════════════════"
echo
c "1;33" "Now check the queue worker terminal / storage/logs/laravel.log for:"
echo "  Ticket ${NUMS[1]} has been assigned to Agent ${AGENTS[1]}."
exit $([ "$FAIL" -eq 0 ] && echo 0 || echo 1)
