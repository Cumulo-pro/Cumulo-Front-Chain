# 🛰️ Cumulo — Technical Incident Report
## Consensus Failure and Validator Key Migration During a Limonata v0.3.4 Rolling Upgrade

**Incident Date:** 23 July 2026
**Impact Window:** **08:43–10:52 UTC** (partial, see timeline)
**Network:** Limonata Testnet (`limonata_10777-1`)
**Severity:** Medium
**Affected Services:**
- Cumulo Limonata validator node
- Cumulo Limonata public RPC / API / EVM / gRPC endpoints (temporary, during recovery)

---

## 1. Summary

On **23 July 2026**, the Cumulo-operated **Limonata validator** attempted a rolling binary upgrade to **v0.3.4**, following the project's own published guidance describing the upgrade as non-consensus-breaking and safe to apply without a coordinated halt.

The binary retrieved from the official release URL differed in content from the binary the majority of other validators received from the same URL that same day. During normal operation shortly after, the validator:

- Produced a genuine **AppHash divergence** at block height 1036402
- Entered a **CONSENSUS FAILURE** panic
- Was left with a local data directory that neither the new nor the previous binary could reload

No pre-upgrade snapshot of the validator's data directory existed. Recovery required migrating the validator's signing key to a secondary node (previously serving only public endpoints) to preserve signing continuity, followed by a full rebuild of a dedicated validator node and a subsequent rebuild of the secondary node back into a clean, non-signing public endpoint.

No double-signing occurred at any point, verified structurally: the signing key was never active on more than one node simultaneously.

---

## 2. Timeline (UTC)

### 08:43:50
Validator processes block 1036401 under the newly installed v0.3.4 binary. CometBFT logs an Optimistic Execution abort during a round 0 to round 1 proposal change, then commits an app hash that differs from the value it had just computed via `FinalizeBlock`.

### 08:43:50
**CONSENSUS FAILURE** panic at height 1036402: the validator's locally recomputed AppHash does not match the value the rest of the network (+2/3) agreed on for the previous block.

### 08:45–08:47
Automatic service restarts fail repeatedly with a store-version mismatch on load. Service stopped manually to end the restart loop.

### 09:00–09:20
Binary inspection shows the installed v0.3.4 build carries empty version/commit metadata. A rollback attempt to the previous binary fails with the identical store-version-mismatch error, confirming the local data directory is unusable by either binary.

### ~09:20
Validator signing key migrated to a secondary node to preserve signing continuity. The affected node taken fully offline.

### 09:24–10:05
A new dedicated validator node is built from a freshly verified v0.3.4 binary (commit confirmed against the value independently reported by other validators). Genesis, peers, and state-sync are configured; the node reaches full sync.

### ~10:20
Validator signing key migrated from the secondary node to the new dedicated validator node, following an overlap-safe sequence (extract, neutralize, activate) to guarantee the key was never live in two places at once.

### 10:24–10:52
Secondary node rebuilt from a clean state as a non-signing public endpoint. Public RPC, API, EVM JSON-RPC, and gRPC endpoints realigned and confirmed responding correctly.

---

## 3. Impact Assessment

- Signed-blocks uptime dipped to **98.44%** during the incident window
- **No double-signing occurred**, verified structurally rather than after the fact
- **No liveness jailing**: the outage window was well within the network's signed-blocks tolerance
- Public RPC/API/EVM/gRPC endpoints were briefly affected during the secondary node's rebuild, confirmed restored by direct endpoint checks matching live chain height

---

## 4. Investigation Findings

### Binary integrity
The v0.3.4 binary obtained from the official release URL reported entirely empty `commit`, `version`, and `build_tags` fields, and differed in file size from the binary independently confirmed to match the commit other validators reported running successfully.

### Vendor acknowledgment
Limonata's own release notes for v0.3.4 state that earlier binaries published under the same release were built without version ldflags, confirming that more than one build existed under the same release asset over time.

### Consensus layer
Raw logs show a round 0 to round 1 proposal change at height 1036401, followed by an Optimistic Execution abort, followed by a discrepancy between the value the node's own `FinalizeBlock` computed and the value it persisted to disk. This is consistent with a defect in how the affected build reconciled state after an Optimistic Execution abort triggered by a round change.

### Storage layer
The subsequent inability to reload the node with either binary produced the error `version of store mismatch root store's version; expected <height> got 0; new stores should be added using StoreUpgrades`. This is a known class of Cosmos SDK defect, previously reported upstream in the Cosmos SDK repository under circumstances where the same binary and the same upgrade succeeded on some runs and failed on others, tied to how new stores are introduced across a binary upgrade rather than something unique to this chain.

### Ruled out
GLIBC version compatibility was considered as a distinguishing factor between binary builds, since the official v0.3.4 release requires GLIBC 2.38. The affected server already runs GLIBC 2.40, which satisfies that requirement regardless of which build was received; this factor does not distinguish between the two binaries and was ruled out.

---

## 5. Root Cause

**Binary discrepancy at the official release URL, compounded by a known Cosmos SDK store-versioning defect class on restart.**

The validator retrieved a binary from Limonata's official release URL that differed from the artifact the majority of the validator set received from the same URL that day, evidenced by empty version metadata, a differing file size, and the vendor's own release notes acknowledging multiple builds existed under the release. This binary produced a genuine AppHash divergence under a specific consensus-timing condition (a round change combined with Optimistic Execution), and the resulting local state could not be reloaded by any available binary due to a store-versioning defect class independently documented in the Cosmos SDK project.

The exact mechanism behind why this validator's download differed from the majority (asset replacement after initial publication, CDN propagation delay, or another cause) has not been independently confirmed. This question has been raised directly with the Limonata team and a response is pending.

---

## 6. Corrective Actions

### Completed
- Affected validator node taken offline; binary rollback confirmed non-viable
- Validator signing key relocated through an overlap-safe migration to a newly built, verified validator node
- Secondary node rebuilt as a clean, non-signing public endpoint with a freshly generated node identity
- Public endpoint configuration fully realigned and individually verified
- Incident reported to the Limonata validator channel with supporting evidence and an open question for the project team

### Recommended Improvements
- Pin and verify the exact expected commit hash as a hard gate before any binary reaches a production path
- Snapshot the validator's data directory before any binary swap, including upgrades described as non-consensus-breaking
- Stage new binaries on a non-critical node before applying them to the active validator
- Treat vendor claims of "safe rolling upgrade" as a hypothesis to verify against a canary node rather than a guarantee

---

## 7. Preventive Enhancements

- Pre-upgrade data-directory snapshotting as a standard step in all binary upgrade runbooks, regardless of vendor guidance
- Canary-node testing of new binaries ahead of any validator-facing rollout
- Explicit, verifiable version/commit gates built into upgrade procedures rather than manual-inspection steps
- Full configuration checklist (consensus-layer and application-layer) when rebuilding any node from scratch, to catch silent divergence from previously working defaults

---

## 8. Final Statement

This incident originated from a discrepancy in the binary artifact distributed at Limonata's official v0.3.4 release URL, which produced a genuine consensus failure on the affected validator and left its local state unrecoverable in place. Cumulo's recovery procedure preserved signing continuity throughout, with no double-signing at any point, through a careful key migration to a rebuilt validator node. Public endpoint service was restored the same day. The open question of why this specific download differed from the majority of the network has been raised with the Limonata team.

---

*Prepared by Cumulo Infrastructure Team*
