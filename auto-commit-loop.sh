#!/usr/bin/env bash
set -euo pipefail

# Directory of this script (assumed to be the repo root).
REPO_DIR="$(cd "$(dirname "$0")" && pwd)"
# Override to change how often commits are attempted.
SLEEP_SECONDS="${SLEEP_SECONDS:-60}"

command -v git >/dev/null 2>&1 || { echo "git is required on PATH"; exit 1; }
command -v codex >/dev/null 2>&1 || { echo "codex CLI is required on PATH"; exit 1; }

generate_message() {
  local staged_diff prompt
  staged_diff="$(git -C "$REPO_DIR" diff --cached)"

  prompt=$(
    cat <<EOF
Write a concise git commit summary line (max 72 chars) for the staged changes.
Reply with one short line only, no quotes or prefixes.

$staged_diff
EOF
  )

  codex "$prompt" | head -n1 | tr -d '\r'
}

while true; do
  cd "$REPO_DIR"

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
