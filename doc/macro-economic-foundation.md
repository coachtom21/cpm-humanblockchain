# Macro-economic foundation — BIS 2.0 (Client specification)

**Source:** Client / governance narrative + technical API & schema brief for Codepixelzmedia / Cursor implementation.

**Note:** This document consolidates overlapping narrative sections from the client handoff. Technical sections (schema, API, rules) follow the client’s specification.

---

## 1. Core principle

- **No custody inside the YAM system.** Fiat/crypto custody stays with **MSB** (money transmitter–style) entities.
- **YAM / XP** = append-only **ledger** (truth layer).
- **LLM Governance Council** = **oracle + clerk** (validation layer): Kalshi-style binary checks, multi-model consensus.
- **Device-driven DAO / LCA** = execution / participation layer (PoD, roles, POC logic).

Separation: **Money → MSBs · Truth → LLMs · Participation → humans/devices.**

---

## 2. Governance structure

### Peace Pentagon → MSB Council selection

Each branch appoints custodial partners (examples from client doc):

| Branch        | Focus                                      |
|---------------|---------------------------------------------|
| Planning      | Central bank + SWIFT alignment              |
| Budget        | Treasury + clearing banks                   |
| Media         | Transparency + reporting rails              |
| Distribution  | Payment rails (Visa/Mastercard)             |
| Membership    | Retail + onboarding banks                   |

### MSB Governance Council (custody layer)

Examples named: SWIFT, DTCC, Visa/Mastercard, BNY Mellon, State Street, Citi, JPMorgan, HSBC, BNP Paribas, etc.

**Roles:** Hold balances, publish MSB statements, execute redemptions, confirm positions on key dates.

### LLM Governance Council (oracle + clerk)

Neutral validator; verifies PoD (Y/N), MSB balances, XP→YAM→fiat integrity; **posts attestations to Human Blockchain** (append-only).

---

## 3. Critical timeline (locked dates)

| Date | Event | Client intent |
|------|--------|----------------|
| **May 16, 2030** | **MSB moment** — funding into MSB custody; opening balances; LLM verifies vs ledger → **Pre-Genesis Custody Verified** block |
| **May 17, 2030** | **Genesis** — XP extinguished → YAM; peg/rules sealed; **immutable genesis block** |
| May–Aug 2030 | Active YAM economy; monthly MSB reporting; LLM posts ledger states |
| **Aug 31, 2030** | **Reconciliation** — custody vs obligations; **zero carryover** verified |
| **Sep 1, 2030** | **Redemption** — e.g. 99% distribution / 1% legacy seed (per client narrative); payouts; tax outputs (1099 variants cited) |
| **Dec 31, 2030** | YAM **trade close** |
| **Jan 1, 2031** | New custodial cycle; device re-registration; **no legacy debt carryforward** (reputation / XP history + legacy seed only) |

---

## 4. Control mechanisms

- Binary proof (YES/NO) on critical events.
- Monthly MSB posting (receipts, obligations, net).
- **No carryover rule** at Aug 31 reconciliation.
- **Multi-model** LLM consensus (no single-model oracle authority).

---

## 5. LCA + DAO

- **LCA:** Legal wrapper — MSB contracts, governance rules, compliance.
- **DAO:** Operational — XP accounting, role assignments, POC logic.

---

## 6. Recommended stack (client)

- PHP 8.3+, MySQL 8 / MariaDB, Redis, S3-compatible storage, cron/queues.
- WordPress admin + **REST `/api/v1/`** for devices/partners.
- Security: JWT/OAuth2, HMAC for MSB posts, device tokens, **immutable hash-chained ledger**.

---

## 7. Domain modules (six logical services)

1. **Identity** — members, devices, Peace Pentagon branches, roles, councils  
2. **Custody** — MSB institutions, accounts, statements, balances, posting windows  
3. **Oracle** — LLM providers, prompts, votes, consensus, attestations  
4. **Ledger** — append-only journal, XP/YAM, custody posts, reconciliation/redemption blocks  
5. **Governance** — councils, seats, resolutions, votes  
6. **Settlement** — redemptions, payouts, confirmations, trade close, new cycle  

---

## 8. Enums (strict, per client)

**Branches:** PLANNING, BUDGET, MEDIA, DISTRIBUTION, MEMBERSHIP  

**Roles (sample):** MEMBER, YAMER, MEGAVOTER, PATRON, COACH, SELLER, BUYER, CAPTAIN, POSTMASTER_GENERAL, MSB_COUNCIL_MEMBER, LLM_COUNCIL_MEMBER, ORACLE_CLERK, TREASURY_ADMIN, AUDITOR  

**Council types:** PEACE_PENTAGON, MSB_COUNCIL, LLM_COUNCIL  

**Institution / asset / statement / oracle / ledger entry types** — as enumerated in the client’s long-form spec (SWIFT_NETWORK, CUSTODIAN_BANK, USD, YAM, XP, PRE_GENESIS_FUNDING, CUSTODY_CONFIRMED, XP_EXTINGUISHMENT, etc.).

---

## 9. Database schema (relational)

The client provided **CREATE TABLE** definitions for at least:

- `organizations`, `branches`, `members`, `devices`, `roles`, `member_roles`
- `councils`, `council_seats`
- `custody_accounts`, `custody_statements`, `asset_balances`
- `oracle_prompts`, `llm_providers`, `oracle_decisions`, `oracle_votes`
- `ledger_blocks`, `ledger_entries`
- `genesis_snapshots`, `reconciliation_snapshots`
- `redemption_requests`, `payout_instructions`, `settlement_confirmations`
- `governance_resolutions`, `governance_votes`
- `system_config`
- `audit_events`

**Foreign keys** and **UUIDs** on key entities as specified. **Hashing:** SHA-256 row hashes; Merkle root optional for block seal; formulas for `ledger_entries` and `ledger_blocks` hashes as in client doc §6.

---

## 10. REST API (prefix `/api/v1/`)

**Auth:** Bearer, optional `X-Signature` / `X-Timestamp` / `X-Request-Id` for HMAC flows.

**Route groups (examples):**

- **Identity:** `POST /members`, `POST /devices`, `POST /members/{uuid}/branch-assignment` (includes `SERENDIPITY_PROTOCOL` in sample payload)
- **Governance:** `POST /governance/councils`, `.../seats`, `POST /governance/resolutions`, `.../vote`
- **Organizations / custody:** `POST /organizations`, `POST /custody/accounts`, `POST /custody/statements`, `POST /custody/statements/{uuid}/balances`
- **Oracle:** `POST /oracle/prompts`, `POST /oracle/decisions`, `POST /oracle/decisions/{uuid}/votes`, `.../finalize`
- **Ledger:** `POST /ledger/entries`, `POST /ledger/blocks/seal`, `POST /ledger/genesis-snapshots`, `POST /ledger/reconciliation-snapshots`
- **Redemption / settlement:** `POST /redemptions`, `POST /redemptions/{uuid}/payout-instructions`, `POST /settlements/confirmations`

**Response envelope:** `{ "success", "data", "meta": { "request_id", "timestamp" }, "errors": [] }`

---

## 11. Required business rules (non-negotiable)

1. **May 16:** All PRE_GENESIS custody statements verified before genesis; else **GENESIS_BLOCKED**.  
2. **May 17:** Genesis only if pre-genesis verified, XP frozen, YAM mint computed, oracle sealed; **YAM_MINT = XP_EXTINGUISHED / XP_PER_YAM** (constants in `system_config`).  
3. **Aug 31:** Reconciliation; **zero_carryover_verified** before redemption.  
4. **Sep 1:** No redemption window if reconciliation not sealed.  
5. **Dec 31:** Trade activity locked per rules.  
6. **Jan 1, 2031:** New cycle; no unresolved debt carryforward.  
7. **Ledger:** No edits after post — **compensating entries** only.  
8. **Oracle:** Multi-provider / multi-seat — no single-model authority.  
9. **Custody:** Every statement hashes through to ledger posts (no hidden balances).

---

## 12. Jobs, cron, consensus

**Queues:** VerifyCustodyStatementJob, OpenOracleDecisionJob, FinalizeOracleDecisionJob, PostLedgerEntryJob, SealLedgerBlockJob, GenerateGenesisSnapshotJob, RunReconciliationJob, OpenRedemptionWindowJob, GenerateTaxReportingBatchJob, CloseTradeWindowJob, OpenNewCycleJob — plus **date-triggered** jobs for May 16/17, Aug 31, Sep 1, Dec 31, Jan 1.

**LLM consensus (default):** e.g. ≥3 votes, ≥2/3 YES, avg confidence ≥ threshold — configurable.

---

## 13. MVP phases (client)

| Phase | Focus |
|-------|--------|
| **1** | Organizations, custody accounts/statements/balances, ledger entries, **manual** oracle, admin UI |
| **2** | Council seats, governance resolutions, automated oracle voting, hash-chained blocks, reconciliation engine |
| **3** | Redemptions, payouts, settlements, tax batches, cycle automation |
| **4** | Device DAO integration, public ledger explorer, multi-sig, LCA hooks, external reporting |

---

## 14. Suggested PHP service layer (namespaces)

`MemberService`, `DeviceService`, `CouncilService`, `CustodyAccountService`, `CustodyStatementService`, `OraclePromptService`, `OracleDecisionService`, `LedgerEntryService`, `BlockSealService`, `GenesisService`, `ReconciliationService`, `RedemptionService`, `PayoutService`, `TaxBatchService` (Laravel-style or WP plugin service classes).

---

## 15. Next deliverables the client asked for

1. **OpenAPI YAML** — full REST contract  
2. **MySQL migration pack** — all tables, indexes, seeds (branches, `system_config` keys)  
3. **Project structure** — Laravel app and/or WordPress plugin layout for the above modules  

---

## 16. Relationship to current WordPress plugin (`cpm-humanblockchain`)

The **existing** plugin today implements a **subset** of “Identity + device + OTP + landing/membership UX” only. This client document describes a **separate large system** (BIS 2.0 ledger, MSB custody, oracle pipeline, milestone cron). Integration would be incremental (e.g. REST namespace, shared member/device IDs, event hooks) — **not** a drop-in replacement for the current plugin without new tables and services.

---

*End of consolidated client specification.*
