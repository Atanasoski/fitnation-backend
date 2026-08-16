---
name: handoff
description: >
  Compact the current conversation into a handoff document for another agent to pick up.
  Use when user says "handoff", "hand off", "write a handoff", "summarise for next session",
  or wants to hand work to a fresh agent/session.
argument-hint: What will the next session be used for?
---

# Handoff

Write a handoff document summarising the current conversation so a fresh agent can continue the work.

## Steps

1. Determine the output path:
   ```bash
   mktemp -t handoff-XXXXXX.md
   ```
   Read that file before writing to it (required by the Write tool).

2. Draft the document using the structure below.

3. Write it to the temp path, then tell the user the path.

## Document structure

```md
# Handoff — <one-line summary of what was worked on>

## Context
<2-4 sentences: why this work exists, what problem it solves>

## What was done
<bullet list of completed steps — no code duplication, reference paths/URLs>

## Current state
<what is working, what is broken, what is blocked>

## Next steps
<ordered list of concrete actions for the next session>

## Key files & references
<paths, URLs, or artifact references — PRDs, ADRs, plans, diffs, issues>

## Suggested skills
<list any skills the next session should invoke, e.g. /tdd, /diagnose>
```

## Rules

- **Do not duplicate** content already captured in other artifacts (PRDs, plans, ADRs, issues, commits, diffs). Reference them by path or URL instead.
- If the user passed arguments, treat them as a description of what the next session will focus on and tailor the doc accordingly — lead with that focus in **Next steps**.
- Keep the document under 150 lines. Dense is better than exhaustive.
- Suggest skills only when there is a clear match to the next session's work.
