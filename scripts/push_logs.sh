#!/bin/bash
# push_logs.sh — Deploy on the TARGET VM
# Pushes recent log lines to the CentralLog dashboard via HTTP POST.
# Add to crontab: * * * * * /opt/centrallog/push_logs.sh
#
# Requires: curl, jq (apt install jq)

DASHBOARD_URL="http://192.168.56.1/api/ingest.php"
TOKEN="change_me_to_a_random_string"

# State dir — tracks what we already sent
STATE_DIR="/tmp/centrallog_state"
mkdir -p "$STATE_DIR"

push_log() {
    local source="$1"
    local file="$2"
    local state_file="$STATE_DIR/$(echo "$file" | tr '/' '_').offset"

    if [[ ! -f "$file" ]]; then
        return
    fi

    # Get current line count
    local total_lines
    total_lines=$(wc -l < "$file")

    # Get last sent offset
    local last_offset=0
    if [[ -f "$state_file" ]]; then
        last_offset=$(cat "$state_file")
    fi

    # Nothing new
    if (( total_lines <= last_offset )); then
        return
    fi

    # Extract new lines (max 200 per push to avoid huge payloads)
    local new_lines
    new_lines=$(tail -n +$((last_offset + 1)) "$file" | head -200)

    if [[ -z "$new_lines" ]]; then
        return
    fi

    # Build JSON payload
    local json_lines
    json_lines=$(echo "$new_lines" | jq -R . | jq -s .)

    local payload
    payload=$(jq -n --arg src "$source" --argjson lines "$json_lines" '{"source": $src, "lines": $lines}')

    # Send to dashboard
    local response
    response=$(curl -s -X POST "$DASHBOARD_URL" \
        -H "X-Ingest-Token: $TOKEN" \
        -H "Content-Type: application/json" \
        -d "$payload" \
        --connect-timeout 5 \
        --max-time 10)

    # Update offset on success
    if echo "$response" | grep -q '"status":"ok"'; then
        local sent_count
        sent_count=$(echo "$new_lines" | wc -l)
        echo $((last_offset + sent_count)) > "$state_file"
    fi
}

# Push each log source
push_log "auth"          "/var/log/auth.log"
push_log "syslog"        "/var/log/syslog"
push_log "apache_access" "/var/log/apache2/access.log"
push_log "apache_error"  "/var/log/apache2/error.log"
push_log "fail2ban"      "/var/log/fail2ban.log"
