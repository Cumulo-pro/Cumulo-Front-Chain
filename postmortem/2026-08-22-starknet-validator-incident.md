> **Note:** this document has been prepared for public GitHub disclosure. All host identifiers below are internal node aliases, not hostnames, IPs, or datacenter references. Co-located internal services are described only by category, never by name, to avoid disclosing details of other infrastructure hosted on the same machines.

# Postmortem: Starknet Validator Attestation Incidents & Redundancy Rollout

**Status:** Resolved
**Affected system:** Starknet validator attestation pipeline (Juno + starknet-staking-v2)
**Impact:** Missed attestations across several epochs on our primary validator node

## Summary

Over the course of this engagement we identified and resolved two independent causes of missed Starknet attestations on our primary validator, and used the investigation as the trigger to roll out a fully redundant, triple active-active validator architecture across geographically distributed hosts to make future attestation misses effectively impossible for a single point of failure.

## Incident 1 — Operator account balance depletion

**Symptom:** The validator's operational account balance dropped below the STRK threshold needed to pay attestation transaction fees, causing several consecutive epochs to be missed even though the node itself was healthy and the staking/delegation balance (visible in the staking wallet) was unaffected.

**Root cause:** The *operational* address (used to pay gas for attestation transactions) is separate from the staked/delegated funds. Delegation balance can look completely healthy while the operational address quietly runs dry, since the two are easy to conflate at a glance.

**Resolution:** Topped up the operational address with STRK. Confirmed recovery via a successful on-chain attestation transaction immediately after funding.

**Follow-up:** Added lower-balance monitoring/alerting on the operational address specifically (independent of delegation-balance dashboards) to prevent silent depletion going forward.

## Incident 2 — Disk I/O contention causing missed attestation window

**Symptom:** Two further epochs were missed on the primary node, this time with the operational account fully funded. Validator logs showed `Invalid transaction nonce` and `Attestation is out of window` errors — the node was trying to attest, but too late.

**Root cause:** Diagnosed via `iostat -x`, correlating disk telemetry with validator logs at the exact timestamps of both missed epochs. The host's disk showed sustained **93% utilization** with **82–177ms** write latency during the failure windows, causing the Starknet node to stall for **6–9 second gaps** at exactly the moments the attestation transaction needed to be submitted. The attestation window is narrow enough that a stall of this size is enough to forfeit the epoch, even though the node recovers moments later.

The contention was caused by other, unrelated internal services co-located on the same host competing for disk I/O — none of which were prioritized relative to the latency-sensitive validator process.

**Resolution:**
1. Paused/stopped the least-critical, non-time-sensitive co-located service that was the single largest disk I/O contributor.
2. Applied `ionice`/`nice` priority elevation directly to the Juno process.
3. Applied systemd resource controls (`CPUQuota`, `Nice`, `IOSchedulingClass`/`IOSchedulingPriority`) to every other co-located internal service on the host, de-prioritizing them relative to the validator stack without shutting them down.
4. Reduced validator log verbosity (`debug` → `info`) after discovering that debug-level logging volume was rotating the systemd journal fast enough to erase evidence of earlier failures — this was hampering our ability to audit incidents after the fact.

**Verification:** Zero missed attestations across the following 10+ hours and all subsequent epochs observed, including epochs immediately following the fix and a full overnight observation window.

## Redundancy rollout

Given the size of our delegation, a single validator instance — however well-tuned — remains a single point of failure. Following the two incidents above, we designed and deployed an **active-active triple-validator architecture**: three independent Juno + starknet-staking-v2 instances, all signing with the *same* operational account, racing to submit each epoch's attestation. Whichever instance's transaction lands on-chain first wins; the other(s) fail harmlessly with a duplicate-transaction/nonce error. This redundancy pattern was confirmed safe by the Nethermind team (Starknet staking validator maintainers).

### Node topology

| Node | Role | Location | L1 provider | Notes |
|---|---|---|---|---|
| **Velia 2** | Primary (pre-existing) | St. Louis | Infura | Original node; subject of both incidents above |
| **BigVelia14T-2** | Redundant #1 | France | Infura (separate project) | Deployed fresh with hardened resource priorities from day one; independent L1 provider account from Velia 2 to avoid correlated L1 outages |
| **VeliaMon-test** | Redundant #2 | St. Louis | Alchemy | Deployed on existing lightly-loaded internal infrastructure (an existing co-located service was resource-throttled to make room, rather than provisioning new hardware); different L1 provider entirely for maximum provider diversity |

Design principles applied to all redundant nodes:
- **Independent L1 (Ethereum) providers** across all three nodes (two separate Infura projects plus one Alchemy project) so that an outage or rate-limit on one provider cannot take down more than one attestor at a time.
- **Juno `--prune-mode`** used on all nodes to minimize on-disk footprint versus a full archive node.
- **Resource priority hardening from initial deployment** (`Nice`, `IOSchedulingClass=realtime`) rather than retrofitted after an incident, learning directly from Incident 2.
- Diagnostic validation (`iostat`, load average, memory headroom) performed on each new host *before* going live to confirm no repeat of the Incident 2 root cause.

### Verification

- BigVelia14T-2: synced, confirmed attesting with the correct shared operational account, and observed winning multiple attestation "races" against the primary node over a 24-hour+ observation window.
- VeliaMon-test: synced, confirmed correct operational account and matching staking/attestation contract addresses, subscribed and tracking the live epoch. Host resource check post-deployment showed no disk I/O contention (well under 1% utilization, sub-millisecond latency) despite other co-located services running unthrottled — confirming the earlier incident's root cause was disk-specific, not host-specific.

## Open follow-ups

- Rotate the shared operational account's private key (currently unrotated since before this engagement).
- Obtain a Starknet feeder gateway API key from StarkWare to speed up future node syncs — informal request pending; nodes currently sync successfully without one, just more slowly during the final catch-up phase.
- Continue monitoring the three-node race outcome distribution over a longer window to build a clearer picture of which node(s) most consistently win attestations.

## Lessons learned

1. **Operational and delegation balances must be monitored separately.** A healthy-looking staking wallet says nothing about the operational account's ability to pay gas.
2. **Disk I/O, not CPU, was the actual bottleneck.** Time-sensitive blockchain workloads sharing a host with other I/O-heavy services need explicit I/O scheduling priority, not just CPU quotas.
3. **Log verbosity has an operational cost.** Debug-level logging rotated critical evidence out of the journal before we could review it — informational level is sufficient for day-to-day operation, with debug reserved for active troubleshooting.
4. **Redundancy is cheaper than it looks.** A second and third validator instance, reusing existing infrastructure where possible, cost a fraction of the delegation at risk from a single missed-epoch streak.
