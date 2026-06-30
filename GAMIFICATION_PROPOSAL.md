# Affiliate Gamification Proposal

**Status:** Draft for review — nothing in this document has been implemented.
**Goal:** Make inhouse and external affiliates push products harder, inspired by how TikTok Shop motivates creators/affiliates to level up (visible tiers, progress bars, leaderboards, milestone badges).
**Hard constraint:** None of the proposals below touch commission calculation, stored commission entries, hierarchy/upline logic, referral registration, or TikTok account request logic. Everything here is an additive, read-only layer on top of existing data unless explicitly called out as "Phase 3 (needs approval)".

---

## 1. Why this works for TikTok Shop (and should work here)

TikTok Shop's affiliate/creator level system relies on three psychological levers:

1. **Visible status** — a badge/tier next to your name that everyone (including your downline) can see.
2. **Near-miss pressure** — a progress bar that shows exactly how close you are to the next tier ("RM2,340 to Gold"), which is proven to drive short-term sales pushes near month-end.
3. **Social proof / competition** — leaderboards that rank you against peers, not just against an abstract sales target.

The current system already has everything needed to compute all three from existing data — no changes to `CommissionCalculatorService` are required.

---

## 2. What already exists (no rebuild needed)

| Data needed | Where it already lives |
|---|---|
| Personal sales per affiliate per month | `commission_entries` (filter `commission_type = 'personal'`, join `commission_runs` for month/year) — already aggregated this way in `AffiliateTeamService::performance()` |
| Order-level dates (for streaks) | `tiktok_orders.time_created` / `time_commission_paid` |
| Team sales ranking | `AffiliateTeamController` + `AffiliateTeamService` already sort by `total_sales` |
| Admin-side "Top Affiliates" | `Admin\DashboardController` already computes top 5 by sales per month |
| Affiliate dashboard free space | `resources/views/affiliate/dashboard.blade.php` — profile card (badge row) and the gap between the stats grid and commission summary section |

No existing badge/tier/rank/streak code was found anywhere in the codebase — this is a clean addition.

---

## 3. Proposed features

### 3.1 Tier Badge (core feature)
A visible rank — e.g. **Bronze → Silver → Gold → Platinum → Diamond** — shown next to the affiliate's name on:
- Affiliate dashboard (profile card)
- Admin affiliate list & detail page
- Team page (so upline sees their downline's tier too — extra peer pressure)

**Tier metric — needs a decision (see Section 6):**
- *Option A — Monthly sales:* tier recalculated every month from that month's personal sales. Resets each month — mirrors TikTok Shop's GMV-tier model exactly. Most "naik turun" pressure, keeps affiliates pushing every month instead of coasting on a high tier earned once.
- *Option B — Lifetime sales:* tier is cumulative and never goes down. Lower pressure but rewards long-term loyalty; risk of affiliates "parking" at a tier and losing urgency.
- *Option C — Combination:* monthly tier badge (resets, drives urgency) + separate permanent lifetime milestone badges (e.g. "Lifetime RM100K Club") that never disappear. Gets both effects but is the most build work.

**Recommendation:** Option A for the main badge (matches TikTok exactly, keeps urgency every month) + a small set of Option-C-style permanent milestone badges layered in Phase 2.

### 3.2 Progress Bar
On the affiliate dashboard: "You're RM X away from [next tier]" with a visual bar. Computed live from the same monthly sales query — no new storage needed beyond the tier thresholds themselves.

### 3.3 Leaderboard
- **Admin side:** expand the existing "Top Affiliates" card from Top 5 → Top 10/20, add explicit rank numbers (#1, #2, #3 visually distinct, e.g. gold/silver/bronze styling).
- **Affiliate side (new):** a "This Month's Top Performers" view affiliates can see themselves — shows top 10 plus "Your rank: #N" highlighted even if they're outside the top 10. Reuses the same ranked query as the admin leaderboard.
- **Team-level leaderboard:** within "My Team", rank downlines by sales (the sort already exists in `AffiliateTeamService`; just needs a rank column and visual treatment).

### 3.4 Milestone Badges / Achievements
One-time, permanent badges for specific accomplishments, e.g.:
- First Sale
- 7-Day Sales Streak ("on fire")
- RM10K Club / RM50K Club / RM100K Club (lifetime)
- Top Recruiter (X direct downlines who made a sale this month)
- Comeback (sale after a 30+ day dry spell)

Computed by a daily scheduled job (the Laravel scheduler cron is already running every minute on the server — this just adds one more scheduled command) that scans `tiktok_orders` / `commission_entries` and inserts into a new `affiliate_badges` table. Purely additive — never deletes or modifies commission data.

### 3.5 Streaks (optional, pairs with 3.4)
Consecutive days/weeks with at least one sale, computed from distinct `time_created` dates per affiliate. Displayed as a small "🔥 5-day streak" indicator. Drives daily habit rather than just monthly targets.

---

## 4. Proposed data model additions (none of this touches existing tables)

```
affiliate_tier_thresholds   -- admin-configurable tier cutoffs (so you can tune them later
                             -- without a code deploy)
  id, tier_name, min_sales_amount, sort_order, icon/color, is_active

affiliate_badges            -- earned milestone badges (append-only, never edited)
  id, affiliate_id, badge_key, earned_at, meta (json, e.g. streak length or amount)

(monthly tier itself does NOT need a stored column — it can be computed on-the-fly
 from commission_entries the same way AffiliateTeamService already does, so the
 badge always reflects current data and never goes stale)
```

This keeps the gamification layer fully decoupled — if it's ever disabled, nothing about commissions, hierarchy, or orders is affected.

---

## 5. Phased rollout

| Phase | Scope | Risk | Touches commission logic? |
|---|---|---|---|
| **Phase 1** | Tier badge + progress bar on affiliate dashboard, admin leaderboard upgraded to Top 10 with ranks | Low — read-only queries, new view code only | No |
| **Phase 2** | Affiliate-facing leaderboard page + milestone badges table + daily badge-check scheduled job | Low-medium — one new table, one new scheduled command | No |
| **Phase 3 (requires explicit approval)** | Tier-based perks with real teeth — e.g. a small commission rate bonus for Gold+ affiliates, or exclusive product/campaign access | Higher — would require changes to `CommissionCalculatorService` and is exactly the kind of change you've asked to be approved explicitly before touching | **Yes — do not start without sign-off** |

Recommendation: ship Phase 1 first, observe whether affiliates actually engage with it (check dashboard visits / WhatsApp chatter), then decide on Phase 2 and whether Phase 3's commission-tied incentive is worth the risk.

---

## 6. Open decisions before implementation starts

1. **Tier metric** — Monthly / Lifetime / Combination (Section 3.1). Recommended: Monthly, with lifetime milestone badges layered in later.
2. **Tier names & thresholds** — e.g. should tiers be named Bronze/Silver/Gold/Platinum/Diamond, or something more brand-specific (e.g. tied to "Role Vision" branding)? What sales amounts (RM) define each cutoff? This should probably be configurable (see `affiliate_tier_thresholds` table above) rather than hardcoded, so you can tune it without needing a developer each time.
3. **Does "sales" for tiering mean personal sales only, or personal + team overriding income?** TikTok Shop uses GMV (sales volume) not commission earned — recommend personal sales volume to keep it simple and consistent with how "Manager" status is already derived (direct downlines).
4. **Visibility of leaderboard** — should affiliates see other affiliates' real names, or anonymized (e.g. "Affiliate #142")? Real names create more competitive pressure but some affiliates may be uncomfortable being publicly ranked low.

---

## 7. What this document does NOT include

- No code changes — this is a proposal only.
- No changes to `CommissionCalculatorService`, `commission_entries`, `commission_runs`, hierarchy/upline logic, referral registration, or TikTok account request flows.
- No decision yet on Phase 3 (commission-tied incentives) — flagged as needing separate, explicit approval.
