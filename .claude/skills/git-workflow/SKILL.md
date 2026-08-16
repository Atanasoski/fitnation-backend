---
name: git-workflow
description: Git branching rules, PR flow, weekly release process, hotfix process, and branch alignment for the muscle-hustle Laravel project. Use when user asks about branching, creating a PR, doing a hotfix, cutting a release, or syncing branches.
---

# Git Workflow

## Branch overview

```
feature/* ──→ dev ──────────────────────────────────────────→ (QA on staging)
                                                                      ↑ auto-sync
fix/*  ──→ main ←── release/YYYY-MM-DD ←── cut-release workflow
              │
              ├──→ dev     (auto backport)
              └──→ staging (auto sync)
```

- All feature work branches off `dev` and targets `dev`
- `staging` is a QA mirror — it reflects `dev` after every release or hotfix landing on `main`
- `main` only receives PRs from `release/*` or `fix/*` branches
- **Never commit directly to `main`, `staging`, or `dev`** — always use PRs

## Branch naming

```
<type>/<description>
```

Examples: `feat/user-auth`, `fix/booking-status`, `chore/update-deps`

## Weekly release process

Releases are driven by a manifest file on `dev`.

**1. Create the manifest** (commit to `dev` before release day):

File: `releases/YYYY-MM-DD.md`

```markdown
- #142
- #156
- #160
```

Rules:
- Only feature/fix PRs merged to `dev` — no sync or alignment PRs
- Co-dependent PRs must both be listed or both be left out
- See `releases/README.md` for full format docs

**2. Trigger the workflow**

Actions → *Cut weekly release branch* → Run workflow → enter the date matching the manifest filename.

The workflow reads the manifest, resolves each PR's squash commit (`mergeCommit.oid`), dry-run cherry-picks onto a temp branch from `main` (fails fast on conflict), then creates `release/YYYY-MM-DD` from `main` and opens a **draft PR to `main`**.

**3. Review the draft PR**, un-draft, get approval, merge with **Create a merge commit**.

**4. After merge**, two workflows fire automatically:
- **Backport** → opens `backport/main-to-dev-{sha}` → `dev`
- **Staging sync** → opens `sync/dev-to-staging-{sha}` → `staging`

Merge both promptly. The person who merged the release PR owns the backport.

### Conflict resolution

| Scenario | Fix |
|---|---|
| A PR conflicts with `main` | Remove it from the manifest, re-run |
| Two PRs conflict with each other | Both stay or both go — split into separate releases |
| A PR can't ship this week | Remove it from the manifest; it stays on `dev` for next time |

## Hotfix process

1. Branch off `main`: `git checkout -b fix/<description> main`
2. Fix, commit, open PR → `main`
3. Merge with **Create a merge commit**
4. Backport and staging sync PRs open automatically — merge both promptly

> The person who merges the hotfix owns the backport PR. Do not leave it open.

## Automated workflows

| Trigger | What it does |
|---|---|
| `release/*` or `fix/*` merges to `main` | Opens backport PR → `dev` |
| `release/*` or `fix/*` merges to `main` | Opens staging sync PR → `staging` |
| Manual dispatch | Reads manifest, cherry-picks, opens draft PR to `main` |

Workflow file conflicts in backport PRs are auto-resolved (takes `main`'s version). Only non-workflow conflicts are flagged for manual resolution.

## Merge strategy

| PR type | Target | Strategy |
|---|---|---|
| Feature / fix | `dev` | **Squash and merge** |
| Release branch | `main` | **Create a merge commit** |
| Hotfix | `main` | **Create a merge commit** |
| Backport (auto) | `dev` | **Create a merge commit** |
| Staging sync (auto) | `staging` | **Create a merge commit** |

**Never squash-merge a backport or sync PR.** Squashing removes shared history with `main`, causing the next merge to diff everything from scratch and generate false conflicts across all files.

## Branch alignment (manual fallback)

Only needed if automated backport/sync PRs weren't merged and branches have significantly diverged. Always use `--no-ff` to guarantee a merge commit.

```bash
git checkout dev && git pull origin dev
git checkout -b chore/main-into-dev-$(date +%Y-%m-%d)
git merge --no-ff origin/main
# Resolve any conflicts
git add . && git commit
git push origin HEAD
gh pr create --base dev --head chore/main-into-dev-$(date +%Y-%m-%d) \
  --title "chore: sync main into dev"
```

## Stash anti-pattern

**Never stash changes from one branch and apply them on a different base branch.**

```bash
# Wrong — contaminates the branch with dev's unreleased history:
git stash
git checkout -b feat/my-feature dev
git stash pop  # ❌ now your branch base is dev

# Right — keep the original main-based branch, open the PR to dev:
gh pr create --base dev --head feat/my-feature
```

The PR diff is clean because `main` is a common ancestor of your branch and `dev`. The release workflow cherry-picks the squash commit, which contains only your changes.
