#!/usr/bin/env bash
set -euo pipefail

base_url="${1:-${BASE_URL:-http://127.0.0.1:8081}}"
base_url="${base_url%/}"
curl_bin="${CURL:-curl}"
failures=0

forbidden_paths=(
  "/medite"
  "/medite/"
  "/storage/uploads/versions/security-probe.txt"
  "/admin/storage/uploads/versions/security-probe.txt"
  "/storage/uploads/demo/work/comparisons/1/source.xhtml"
  "/admin/storage/uploads/demo/work/comparisons/1/source.xhtml"
  "/uploads/versions/security-probe.txt"
  "/uploads/_quarantine/security-probe.txt"
  "/uploads/__medite_inputs/security-probe.txt"
  "/uploads/demo/work/comparisons/1/source.xhtml"
  "/test-comparison.php"
  "/upload_functions.php"
  "/php/"
  "/dev/php/"
  "/.env"
  "/.git/config"
  "/uploads/"
  "/uploads_images/"
  "/uploads/pdf/"
)

header_paths=(
  "/"
  "/health"
  "/admin/login"
)

record_ok() {
  printf 'OK   %s\n' "$1"
}

record_fail() {
  failures=$((failures + 1))
  printf 'FAIL %s\n' "$1"
}

http_status() {
  local path="$1"
  local status

  status="$("$curl_bin" -k -sS -o /dev/null -w '%{http_code}' "${base_url}${path}" 2>/dev/null || true)"
  if [[ -z "$status" ]]; then
    status="000"
  fi

  printf '%s' "$status"
}

headers_for() {
  local path="$1"
  "$curl_bin" -k -sS -D - -o /dev/null "${base_url}${path}" 2>/dev/null | tr -d '\r' || true
}

assert_forbidden_path() {
  local path="$1"
  local status
  local status_num

  status="$(http_status "$path")"
  if [[ "$status" =~ ^[0-9]{3}$ ]]; then
    status_num=$((10#$status))
  else
    status_num=0
  fi

  if (( status_num >= 400 )); then
    record_ok "${path} blocked with HTTP ${status}"
  else
    record_fail "${path} unexpectedly served with HTTP ${status}"
  fi
}

assert_no_powered_by() {
  local path="$1"

  if headers_for "$path" | grep -Eiq '^X-Powered-By:'; then
    record_fail "${path} exposes X-Powered-By"
  else
    record_ok "${path} does not expose X-Powered-By"
  fi
}

cookie_header() {
  local path="$1"
  local cookie_name="$2"

  headers_for "$path" | awk -v name="$cookie_name" '
    BEGIN { IGNORECASE = 1 }
    $0 ~ "^Set-Cookie: " name "=" { print; exit }
  '
}

assert_cookie_flags() {
  local path="$1"
  local cookie_name="$2"
  local cookie
  local lowered

  cookie="$(cookie_header "$path" "$cookie_name")"
  if [[ -z "$cookie" ]]; then
    record_fail "${path} did not set ${cookie_name}"
    return
  fi

  lowered="$(printf '%s' "$cookie" | tr '[:upper:]' '[:lower:]')"

  if [[ "$lowered" == *"; secure"* ]]; then
    record_ok "${cookie_name} has Secure"
  else
    record_fail "${cookie_name} missing Secure"
  fi

  if [[ "$lowered" == *"; samesite=lax"* ]]; then
    record_ok "${cookie_name} has SameSite=Lax"
  else
    record_fail "${cookie_name} missing SameSite=Lax"
  fi

  if [[ "$cookie_name" == "variance_admin_session" ]]; then
    if [[ "$lowered" == *"; httponly"* ]]; then
      record_ok "${cookie_name} has HttpOnly"
    else
      record_fail "${cookie_name} missing HttpOnly"
    fi
  fi
}

printf 'Probing %s\n\n' "$base_url"

printf 'Forbidden public paths\n'
for path in "${forbidden_paths[@]}"; do
  assert_forbidden_path "$path"
done

printf '\nRuntime headers\n'
for path in "${header_paths[@]}"; do
  assert_no_powered_by "$path"
done

printf '\nAdmin cookie flags\n'
assert_cookie_flags "/admin/login" "XSRF-TOKEN"
assert_cookie_flags "/admin/login" "variance_admin_session"

printf '\n'
if (( failures > 0 )); then
  printf '%d security probe(s) failed.\n' "$failures"
  exit 1
fi

printf 'All security probes passed.\n'
