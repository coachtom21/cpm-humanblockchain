# NWP Gateway Configuration

## Purpose

The NWP scan is not the transaction itself. It is the onboarding gateway that prepares a person to participate in the YAM JAM game.

The YAM-is-ON universal QR remains the separate buyer/seller pledge-confirmation tool for the actual $30 pledge and the $10.30 community value allocation.

**So the rule is:**
- **NWP** = onboarding, identity, role readiness, print rights
- **YAM-is-ON** = delivery confirmation, pledge allocation, ledger event

---

## 1. Recommended User Flow

### A. Seller hands participant an NWP
Participant scans the NWP QR code.

### B. NWP landing page opens
This page should do four things in order:
1. Register device
2. Accept Discord Gracebook invite
3. Select membership level
4. Unlock print / issuance options

That is the entire gateway.

### C. Once complete
The member becomes "ready status" in the system and can:
- receive printed NWP
- issue NWP as Individual, POC, or Guild
- participate in referral flow
- move into future YAM-is-ON pledge-confirmed exchanges

---

## 2. Best Current Framework Structure

Use a 4-step onboarding wizard after NWP scan.

### Step 1 — Device Registration
Capture only what is needed:
- device ID hash
- timestamp
- geo-location
- email
- mobile number
- QRTiger v-card link if available
- referral source NWP issuer ID

**Output:** Create a `device_registered = true` status. This is the first trust point in the append-only ledger.

### Step 2 — Discord Gracebook Acceptance
After device registration, show:
- "Join Discord Gracebook"
- invite link
- "I accepted" button
- optional OAuth/verification handshake if possible

If Discord API confirmation is hard at first, use:
- invite click tracking
- manual confirmation flag
- later Discord bot reconciliation

**Output:** Create a `discord_join_pending` or `discord_joined` status.

### Step 3 — Membership Level Selection
Present only three choices, very clearly:
- **YAM'er** — free
- **MEGAvoter** — $12 annual pledge
- **Patron** — $30 monthly pledge

But under your current language, these are still pledge selections, not immediate payment obligations.

Store:
- selected membership class
- selected date
- pledge status = pending / active / observer
- maturity clock if needed later

**Output:** Create a `membership_selected` record.

### Step 4 — NWP Print + Issuance Rights
Once the first three are done, unlock the NWP utility page:

User can:
- print NWP
- download digital NWP
- choose issuance type:
  - Individual
  - POC
  - Guild

**Important logic:** This issuance selection should happen at time of delivery by seller to participant, not permanently at registration.

So the system should store two separate ideas:
- **Permanent profile default** — preferred issuance mode
- **Transaction-time issuance** — actual issuance used on a specific delivery

That gives flexibility and keeps the system aligned with your field logic.

---

## 3. Core UX Recommendation

### One QR, one dashboard

When NWP is scanned, the user should land on a single page called:

**NWP Gateway**

with four large cards:
1. Register Device
2. Join Gracebook
3. Choose Membership
4. Print / Issue NWP

Each card shows status:
- Not Started
- In Progress
- Complete

At the top show a progress bar:
- 0/4 complete
- 1/4 complete
- 4/4 complete — Ready to Play

That is much better than scattering onboarding across multiple pages.

---

## 4. Recommended WordPress Plugin Modules

For Codepixelzmedia, I would split this into these plugin modules:

| Module | Name | Handles |
|--------|------|---------|
| **A** | NWP Scan Router | QR scan landing, issuer reference, referral attribution, device fingerprint intake, redirect into onboarding wizard |
| **B** | Device Registry | device registration, geolocation, timestamp logging, registration status, repeat scan recognition |
| **C** | Discord Gracebook Connector | Discord invite link tracking, acceptance confirmation, Discord user mapping, grace-period pending status |
| **D** | Membership Selector | YAM'er / MEGAvoter / Patron selection, status flagging, pledge-state labeling, upgrade / downgrade path |
| **E** | NWP Print Manager | printable NWP generation, QR payload creation, issuer type selection, PDF/download output, issuance history |
| **F** | Referral Engine | who invited whom, residual annual referral bonus calculations, active membership dependency, lineage tree |
| **G** | Issuance Ledger | individual issuance, POC issuance, guild issuance, seller-to-participant handoff records, append-only trust record |

---

## 5. Database Structure Recommendation

These are the minimum tables I would recommend.

### wp_nwp_devices
Stores:
- id
- user_id
- device_hash
- email
- phone
- geo_lat
- geo_lng
- registered_at
- registration_status

### wp_nwp_discord_status
Stores:
- id
- user_id
- discord_username
- discord_user_id
- invite_code
- invite_clicked_at
- accepted_at
- status

### wp_nwp_memberships
Stores:
- id
- user_id
- membership_level
- pledge_type
- status
- selected_at
- effective_at
- maturity_date

### wp_nwp_referrals
Stores:
- id
- referrer_user_id
- referred_user_id
- source_nwp_id
- relationship_status
- annual_bonus_status
- active_membership_required
- created_at

### wp_nwp_profiles
Stores:
- id
- user_id
- default_issuance_type
- default_branch
- default_role
- print_enabled
- nwp_design_version

### wp_nwp_issuance_events
Stores:
- id
- issuer_user_id
- recipient_user_id
- issuance_type // individual / poc / guild
- poc_id
- guild_id
- delivery_context
- issued_at
- device_hash
- geo_lat
- geo_lng

*This is the most important transactional-prep table for NWP.*

---

## 6. Status Model

Use a simple readiness model.

### User readiness states
- observer
- device_registered
- discord_pending
- discord_complete
- membership_selected
- print_enabled
- issuer_ready

### Suggested rule
A user becomes `issuer_ready` only when:
- device registered = true
- Discord accepted = true
- membership selected = true

Then the print button unlocks fully.

---

## 7. Issuance Logic at Time of Delivery

This is where your framework becomes very strong.

When seller physically gives NWP to participant, seller should be able to tap:

**"Issue This NWP As"**
- Individual
- POC
- Guild

That decision should be captured at the handoff moment.

### Why this is best
Because your system treats NWP as:
- an invitation
- an endorsement
- a referral source
- a trust marker

So the issuer context matters.

**Example:** A seller may personally hand the NWP to someone, but the actual issuance can be:
- from the seller personally
- on behalf of their POC
- on behalf of a guild event or organizer group

That should not be hard-coded too early.

---

## 8. How It Connects to YAM-is-ON

NWP onboarding should feed YAM-is-ON, but never replace it.

### Clean relationship

**NWP creates:**
- identity
- community access
- referral linkages
- print rights
- issuer context

**YAM-is-ON later creates:**
- proof-of-delivery confirmation
- buyer/seller role confirmation
- $30 pledge event
- $10.30 community value allocation
- seller margin / COGS ledger relationship

That separation keeps the system understandable.

---

## 9. Recommended Page Architecture

### Public page
- `/nwp-gateway/`

### Wizard steps
- `/nwp-gateway/device-registration/`
- `/nwp-gateway/join-gracebook/`
- `/nwp-gateway/membership-selection/`
- `/nwp-gateway/print-and-issue/`

### Member dashboard
- `/my-nwp/`

Shows:
- registration status
- Discord status
- membership level
- referral count
- annual residual bonus status
- print/download button
- recent issuance activity

---

## 10. Referral Bonus Handling

Because you want annual residual bonuses tied to active memberships, structure it this way:

### Referral rule
Referrer earns residual bonus only if:
- referred member remains active
- membership state qualifies
- annual snapshot date is met

### Data needed
Each referral record should check:
- referred member active?
- referrer active?
- class of membership?
- annual award period?

This should be calculated in a **scheduled annual job**, not on every page load.

---

## 11. Best MVP Configuration

### For a first deploy, keep it simple:

**MVP includes:**
- NWP scan page
- device registration form
- Discord invite click + confirmation button
- membership level selection
- printable NWP PDF/download
- manual issuance type selector
- referral tracking
- admin dashboard

### Delay for phase 2:
- automated Discord verification
- advanced POC assignment
- guild hierarchy automation
- deep branch logic
- residual payout automation
- Kalshi mirror overlays
- MEGAgrant scoring

That will help Codepixelzmedia ship faster.

---

## 12. Best Admin Dashboard Views

Cursor and Codepixelz should build these admin panels:

| Admin View | Description |
|------------|-------------|
| **Admin View 1 — Onboarding Funnel** | scans, device registrations, Discord joins, membership selections, print activations |
| **Admin View 2 — Issuance Activity** | individual issuances, POC issuances, guild issuances, top issuers |
| **Admin View 3 — Referral Tree** | who invited whom, active vs inactive referrals, annual bonus eligibility |
| **Admin View 4 — Readiness Report** | users ready for YAM-is-ON, users pending Discord, users pending membership selection |
