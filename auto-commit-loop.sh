#!/usr/bin/env bash
set -euo pipefail

# Directory of this script (assumed to be the repo root).
REPO_DIR="$(cd "$(dirname "$0")" && pwd)"
# Override to change how often commits are attempted.
SLEEP_SECONDS="${SLEEP_SECONDS:-60}"
# Allow overriding where Codex stores its config/auth to avoid global conflicts.
CODEX_HOME="${CODEX_HOME_OVERRIDE:-"$REPO_DIR/.codex-autocommit"}"
CODEX_DATA_DIR="$CODEX_HOME/.codex"
REAL_HOME="$HOME"

command -v git >/dev/null 2>&1 || { echo "git is required on PATH"; exit 1; }
command -v codex >/dev/null 2>&1 || { echo "codex CLI is required on PATH"; exit 1; }

bootstrap_codex_home() {
  mkdir -p "$CODEX_DATA_DIR"

  # Copy auth from the real home if the sandboxed codex home lacks it.
  if [[ ! -f "$CODEX_DATA_DIR/auth.json" && -f "$REAL_HOME/.codex/auth.json" ]]; then
    cp "$REAL_HOME/.codex/auth.json" "$CODEX_DATA_DIR/auth.json"
  fi

  # Write a minimal, valid config to avoid invalid defaults (e.g., model_reasoning_effort).
  if [[ ! -f "$CODEX_DATA_DIR/config.toml" ]]; then
    cat >"$CODEX_DATA_DIR/config.toml" <<'EOF'
model = "gpt-5-codex"
model_reasoning_effort = "medium"
EOF
  fi
}

sanitize_message() {
  # Strip ANSI and control characters, take the first non-empty line.
  printf '%s' "$1" |
    tr -d '\r' |
    perl -pe 's/\e\[[0-9;]*[A-Za-z]//g; s/\e]0;.*?\a//g; s/[\x00-\x08\x0B-\x1F\x7F]//g' |
    sed -e '/^$/d' | head -n1 | sed 's/^[[:space:]]*//;s/[[:space:]]*$//'
}

fallback_message() {
  local files msg
  files="$(git -C "$REPO_DIR" diff --cached --name-only | head -n5 | tr '\n' ' ')"
  msg="chore: automated commit"
  if [[ -n "$files" ]]; then
    msg="chore: update ${files% }"
  fi
  printf '%s' "$msg"
}

run_codex() {
  local prompt="$1" tmp output
  tmp="$(mktemp)"

  # Use a sandboxed Codex home to avoid TTY errors and bad global config; pipe prompt directly.
  if HOME="$CODEX_HOME" codex exec --color=never --output-last-message "$tmp" --full-auto - >/dev/null 2>&1 <<<"$prompt"; then
    output="$(cat "$tmp" 2>/dev/null || true)"
  else
    output=""
  fi

  rm -f "$tmp"
  printf '%s' "$output"
}

generate_message() {
  local staged_diff prompt raw clean
  staged_diff="$(git -C "$REPO_DIR" diff --cached)"

  prompt=$(
    cat <<EOF
Write a concise git commit summary line (max 72 chars) for the staged changes.
Reply with one short line only, no quotes or prefixes.

$staged_diff
EOF
  )

  raw="$(run_codex "$prompt" || true)"
  clean="$(sanitize_message "$raw")"

  if [[ -z "$clean" ]]; then
    echo "Codex failed to return a clean message; falling back." >&2
    clean="$(fallback_message)"
  fi

  printf '%s' "$clean"
}

while true; do
  cd "$REPO_DIR"

  bootstrap_codex_home

  if [[ -z "$(git status --porcelain)" ]]; then
    printf '%s | No changes to commit, sleeping %ss\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$SLEEP_SECONDS"
    sleep "$SLEEP_SECONDS"
    continue
  fi

  git add -A

  msg="$(generate_message)"
  msg="${msg//$'\n'/ }"
  if [[ -z "$msg" ]]; then
    msg="chore: automated commit"
  fi

  if git commit -m "$msg"; then
    branch="$(git rev-parse --abbrev-ref HEAD)"
    if ! git push origin "$branch"; then
      echo "Push failed; will retry after sleeping."
    fi
  else
    echo "Commit failed; will retry after sleeping."
  fi

  sleep "$SLEEP_SECONDS"
done
