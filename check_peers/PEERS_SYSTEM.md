# Cumulo Live Peers. Technical Documentation

> A curated, scored, and continuously verified peer list for Cosmos (CometBFT) chains.  
→ [Available endpoints and usage](#6-public-api)

_Last reviewed: 2026-11-14. Five chains publish only peers that completed a real P2P handshake and two more are being observed; the current status per chain is in [section 7](#7-p2p-handshake-probe)._

---

## Table of Contents

1. [Why This Exists](#1-why-this-exists)
2. [Architecture & Data Flow](#2-architecture--data-flow)
3. [Selection Algorithm & Scoring](#3-selection-algorithm--scoring)
4. [How to Appear on the List](#4-how-to-appear-on-the-list)
5. [Contributing to the Validators JSON](#5-contributing-to-the-validators-json)
6. [Public API](#6-public-api)
7. [P2P Handshake Probe](#7-p2p-handshake-probe)
8. [Configuration](#8-configuration)

---

## 1. Why This Exists

Most peer lists in the Cosmos ecosystem are either static (they go stale within hours) or simple snapshots of what a single node sees at a given moment via `/net_info`. Neither approach answers the question that actually matters to a node operator with connectivity problems:

> *Which peers have been consistently reachable from outside, across multiple independent sources, over the last several hours?*

Cumulo Live Peers goes further by combining four distinct data sources per chain:

- **Our own node's `/net_info`**: the peers our node is actively connected to right now
- **`/net_info` of trusted validator RPCs**: the peers seen simultaneously by other independent nodes in the ecosystem, drawn from Cumulo's own validator resource lists maintained for each chain
- **`addrbook.json` from those same validators**: a pool of known peer candidates used as a reserve for future discovery
- **Peer strings contributed directly by validators**: additional p2p-verified candidates

As part of our infrastructure services, Cumulo maintains up-to-date resource lists for each supported chain - including RPCs, addrbooks, seeds, and peer addresses - which we publish openly for the community at [cumulo.pro/services](https://cumulo.pro/services/). These same resources feed the peer discovery system, ensuring the data sources are curated, trusted, and kept current.

This multi-source approach means that a peer appearing in the `/net_info` of several independent validators simultaneously carries a much stronger signal than one seen only by our node. The more validators contribute an RPC to the system, the more precise this signal becomes.

Beyond discovery, every IPv4 peer is **TCP-probed directly on the p2p port** to verify external reachability, a **10-observation sliding buffer** (~5 hours) filters out unstable peers, and a **multi-factor score** determines the final ranking.

An open TCP port does not prove that a peer completes the CometBFT P2P handshake or has free inbound slots. To close that gap, a second probe that performs the real handshake runs on every chain where it has been enabled, and those lists publish only peers that completed it. Which chains, and since when, is in the status table of [section 7](#7-p2p-handshake-probe).

---

## 2. Architecture & Data Flow

### Infrastructure

| Component | Role |
|---|---|
| Collector | Systemd timer wakes it every 6 min; each chain runs at its own minute offset, so every chain is refreshed every 30 min. Aggregates all sources and publishes results |
| Cosmos node | Primary peer source via `/net_info` |
| Web server / Nginx | Serves `peers.cumulo.me` |
| Validators JSON | Cumulo-maintained resource list per chain, hosted on GitHub |
| P2P probe (`p2pprobe`) | Go binary run by the collector: one real P2P handshake per peer (a CometBFT build and a Tendermint2 build for Gno.land). Enabled per chain: see the status table in [section 7](#7-p2p-handshake-probe) |

### Tier System

Every peer candidate is classified into one of two tiers before processing:

**TIER 1 - Active verified peers**
- Source: `/net_info` of our node or external validator RPCs
- The peer is actively connected to at least one known node right now
- IPv4: mandatory TCP probe on the p2p port
- IPv6: treated as verified if `is_outbound=true` on our own node (active connection = sufficient proof). Kept in the store for scoring, but never published (see the IPv6 note in section 3)
- Buffer weight: `1.0` per successful observation
- Score multiplier: `×1.2`

**TIER 2 - Reserve candidates**
- Source: `addrbook.json` and `peers` fields from the validators JSON
- Known to other nodes historically but not verified as currently active
- IPv4: TCP-probed every cycle to build a reachability history in the store
- IPv6: discarded - no outbound IPv6 connectivity from the collector currently
- Only the 200 most recent entries from each addrbook (by `last_attempt`) are processed
- Buffer weight: `0.5` per successful observation
- Score multiplier: `×1.0`

**Why seed nodes are excluded:**  
Seed nodes are specifically designed to connect briefly, share their known peer list, and disconnect. They do not maintain persistent connections and are therefore not useful as `persistent_peers`. The `seed` field in the validators JSON is used for documentation purposes but is intentionally ignored by the collector when building the candidate pool.

**The role of TIER 2:**  
With `buffer_size=10` and `inclusion_threshold=7.0`, the maximum sum a TIER2 peer can ever accumulate in the sliding buffer is `10 × 0.5 = 5.0` - which never reaches the threshold of 7.0. TIER2 peers are therefore **never directly published**. Their purpose is to build a pre-vetted reserve: TCP reachability history and store presence are already accumulated by the time a peer transitions to TIER1. This transition happens automatically when the peer appears in any `/net_info` source - at that point it is reclassified as TIER1 and begins accumulating full-weight observations toward the publication threshold.

**What happens when a TIER1 peer disappears from `/net_info`:**  
The tier classification (`TIER1`/`TIER2`) in the store is never downgraded - it reflects the best known state of the peer. However, every cycle where the peer does not appear in any source adds `0.0` to its sliding buffer. As these zero observations accumulate, `sum(buffer_10)` drops below the threshold and the peer is automatically removed from the published list. If the peer reappears in a `/net_info` later, it recovers its TIER1 status immediately without having to rebuild from TIER2, and resumes accumulating full-weight observations.

### Data Flow

```
GitHub (validators_testnet.json / validators_mainnet.json)
      │
      │  fetched every cycle (raw URL)
      ▼
┌─────────────────────────────────────────────────────────────┐
│                       COLLECTOR                             │
│                                                             │
│  Source 1: GET /net_info  (our node)          → TIER1       │
│            (also counts in source_count_max)                │
│  Source 2: GET /net_info  (validator RPCs)    → TIER1       │
│  Source 3: GET addrbook.json (top 200 recent) → TIER2       │
│  Source 4: peers field in validators JSON     → TIER2       │
│            (seed field is intentionally ignored)            │
│          │                                                  │
│          ▼  Deduplication pool                              │
│    - TIER1 is never downgraded in the store                 │
│    - source_count incremented per net_info appearance       │
│    - is_outbound accumulated with OR                        │
│          │                                                  │
│          ▼  Hard filters                                    │
│    - Discard private/loopback IPs                           │
│    - IPv6 TIER1 outbound (own node) → pre-verified          │
│    - IPv6 all other cases → discarded                       │
│    - Discard port 0 or malformed addresses                  │
│          │                                                  │
│          ▼  TCP probe (max 20 parallel, 3s timeout)         │
│    - Direct TCP handshake on the p2p port                   │
│    - RPC of the peer is never touched                       │
│          │                                                  │
│          ▼  Sliding buffer update (float values)            │
│    - TIER1 success → +1.0 | TIER1 failure → +0.0           │
│    - TIER2 success → +0.5 | TIER2 failure → +0.0           │
│    - Peer absent from all sources this cycle → +0.0         │
│          │                                                  │
│          ▼  Multi-factor scoring                            │
│    - tier × stability × outbound × source_diversity         │
│    - diversity scales with validator RPC count in JSON      │
│          │                                                  │
│          ▼  Top 20 selection with geographic diversity      │
│          │                                                  │
│          ▼                                                  │
│    Publishes peers.txt and peers.json to output/            │
└─────────────────────────────────────────────────────────────┘
      │
      │  Nginx serves peers.cumulo.me
      ▼
https://peers.cumulo.me/peers/<chain>/<network>/peers.json
      │
      │  fetch() from dashboard
      ▼
peers.cumulo.me  (renders copyable widget with geo-sorted peers)
```

### Store JSON Schema

Each chain maintains a persistent store file (`store_<chain_id>.json`) updated every cycle:

```json
{
  "chain_id": "provider",
  "last_updated": 1747234316.0,
  "peers": {
    "<node_id>": {
      "node_id":              "a3825c8fb9b8...",
      "ip":                   "54.37.31.127",
      "port":                 14956,
      "region":               "EU",
      "tier":                 "TIER1",
      "ipv6":                 false,
      "first_seen":           1747000000.0,
      "last_seen":            1747234316.0,
      "observations_total":   48,
      "observations_present": 48,
      "tcp_ok_count":         48,
      "outbound_count":       45,
      "source_count_max":     7,
      "buffer_10":            [1.0, 1.0, 1.0, 1.0, 1.0, 1.0, 1.0, 1.0, 1.0, 1.0]
    }
  }
}
```

| Field | Description |
|---|---|
| `tier` | `TIER1` or `TIER2`. Reflects the best known state - never downgraded. |
| `ipv6` | `true` if IPv6. These peers skip TCP probe. |
| `buffer_10` | Sliding window of 10 float values: `1.0` TIER1 success, `0.5` TIER2 success, `0.0` failure or absence. |
| `source_count_max` | Maximum number of distinct `/net_info` sources (our node + validator RPCs) that reported this peer in the same cycle. Core input for the diversity bonus. |
| `outbound_count` | Cycles where this peer was outbound in at least one source. |
| `observations_total` | Total cycles since first detection. |
| `tcp_ok_count` | Total successful TCP probes accumulated. |

---

## 3. Selection Algorithm & Scoring

### TCP Reachability Probe

Before any peer enters the sliding buffer, the collector verifies that its p2p port is externally reachable via a direct TCP probe:

```python
socket.create_connection((ip, port), timeout=3)
```

This performs a standard three-way TCP handshake:

```
Collector  →  SYN        →  Peer (ip:26656)
Collector  ←  SYN-ACK    ←  Peer
Collector  →  ACK        →  Peer
              connection established ✓
Collector  →  FIN        →  Peer  (closed immediately)
```

If the peer responds with `SYN-ACK` within 3 seconds → probe successful (`tcp_ok=True`).  
If it does not respond, rejects the connection, or times out → probe failed (`tcp_ok=False`).

**What the probe verifies:**
- The p2p port is open and listening
- The firewall accepts incoming connections from external IPs
- The peer is reachable from outside its local network

**What the probe does not verify:**
- Whether the node is synchronized with the chain
- Whether the CometBFT p2p protocol handshake succeeds
- Latency or bandwidth of the connection

TCP reachability is the minimum signal, and the right first filter: a closed port can never work. It is not a sufficient one. A peer can accept the TCP connection and still never answer the CometBFT handshake (wrong or overloaded process, a proxy in front of the port, connection filters), or complete it and then close the connection because it has no free inbound slots. Measurements on Celestia mainnet showed that this affects a large share of the peers that pass the TCP probe (details in [section 7](#7-p2p-handshake-probe)). The TCP probe therefore stays as the first filter, and on Celestia mainnet the handshake probe is the second.

**Why not probe the RPC port instead?**  
Many validators keep their RPC port closed for security reasons. Probing the RPC would systematically penalize the most professionally configured nodes. The p2p port is the only port that every functioning node must keep open.

**Why latency is not measured**  
The TCP handshake time could technically be measured, but it would reflect the latency between the collector's location and the peer - not between the operator and the peer. A node in Singapore measured from St. Louis would show ~200ms, while an operator in Bangkok would experience ~5ms to the same peer. Publishing latency as a quality signal would penalize geographically distant peers unfairly. Instead, the dashboard sorts peers by geographic proximity to the visitor, so each operator sees the peers most likely to have low latency for their specific location. The handshake probe described in section 7 also records the handshake duration per peer; it has the same limitation (measured from the collector) and is not published as a quality signal.

**IPv6 peers**  
The collector currently lacks outbound IPv6 connectivity. IPv6 peers from our own node that are `is_outbound=true` are treated as verified without a TCP probe (the active CometBFT connection is taken as proof of reachability) and are kept in the internal store for scoring and statistics. **They are not published**: `peers.txt` and `peers.json` contain IPv4 peers only, because CometBFT rejects unbracketed IPv6 addresses in `persistent_peers` (`too many colons in address`). Full IPv6 TCP probe support is pending a network upgrade.

**Probe parallelism and timing**  
Up to 20 probes run in parallel with a 3-second timeout each. With pools of 600-800 candidates (typical for mainnet), the probe phase takes approximately 60-90 seconds per cycle. Results feed directly into the sliding buffer update described below.

### Layer 1 - Sliding Window Filter (entry criterion)

A peer is eligible for publication only when the sum of its `buffer_10` reaches the configured threshold:

```
sum(buffer_10) >= inclusion_threshold  (default: 7.0)
```

| Scenario | Buffer values | Sum | Published? |
|---|---|---|---|
| TIER1, 10/10 successful probes | [1.0 × 10] | 10.0 | ✅ Yes |
| TIER1, 7/10 successful probes | [1.0 × 7, 0.0 × 3] | 7.0 | ✅ Yes |
| TIER1, 6/10 successful probes | [1.0 × 6, 0.0 × 4] | 6.0 | ❌ No |
| TIER2, 10/10 successful probes | [0.5 × 10] | 5.0 | ❌ No (max possible is 5.0 - never published) |
| TIER1, was published, now absent 3+ cycles | buffer fills with 0.0 over time | drops below 7.0 | ❌ Removed (tier unchanged in store) |

A peer is **removed** from the published list when its buffer sum drops below the threshold after an update - this happens naturally as failed or absent observations (0.0) replace older successful ones in the sliding window.

A peer is **purged** from the store entirely if it has not appeared in any source for 5 consecutive days. This keeps the store lean and prevents accumulation of permanently offline nodes.

### Layer 2 - Multi-Factor Score (ordering criterion)

All peers that pass the sliding window are scored and ranked:

```
score = tier_bonus × stability × outbound_bonus × diversity_bonus
```

#### tier_bonus
```
TIER1 → 1.2
TIER2 → 1.0
```

#### stability
```
stability = sum(buffer_10) / len(buffer_10)
```

| Case | Value |
|---|---|
| TIER1 perfect (10/10) | 1.0 |
| TIER1 at threshold (7/10) | 0.7 |
| TIER2 perfect (10/10 × 0.5) | 0.5 (max possible) |

#### outbound_bonus
```
outbound_ratio = outbound_count / observations_total
outbound_bonus = 1.1  if outbound_ratio >= 0.5
               = 1.0  otherwise
```

A peer that has been outbound in the majority of observations was actively chosen by multiple nodes - a meaningful signal of reliability.

#### diversity_bonus
```
diversity_bonus = 1.0 + log2(source_count_max + 1) / log2(total_validators + 1)
```

Range: `1.0` (seen in 1 source) → `2.0` (seen in all sources simultaneously)

`source_count_max` is the maximum number of distinct `/net_info` sources - our own node plus all validator RPCs - that reported this peer **in the same cycle**. The total number of possible sources is therefore `validators_in_JSON + 1` (our node always counts as an independent source).

The `diversity_bonus` formula uses `total_validators + 1` as the denominator to keep it consistent with `source_count_max`, so that a peer seen by every possible source always achieves the maximum bonus of `2.0` and a maximum score of `2.64`:

```
# Example: mainnet with 6 validators in JSON
total_sources = 6 + 1 = 7  (6 validator RPCs + our own node)

peer seen in 7/7 sources → diversity_bonus = 1.0 + log2(8)/log2(8) = 2.0   → score 2.64
peer seen in 6/7 sources → diversity_bonus = 1.0 + log2(7)/log2(8) = 1.936  → score 2.5552
peer seen in 5/7 sources → diversity_bonus = 1.0 + log2(6)/log2(8) = 1.861  → score 2.4574
peer seen in 1/7 sources → diversity_bonus = 1.0 + log2(2)/log2(8) = 1.333  → score 1.76
```

This bonus scales automatically with the number of RPCs in the validators JSON. With 4 validators, the scoring differences are moderate. With 15+ validators, the gap between a peer seen in 3 sources vs 12 sources becomes highly significant and provides meaningful discrimination.

#### Score examples (mainnet with 6 validators in JSON → 7 total sources)

| Peer | Tier | Stability | Outbound | Sources | Score |
|---|---|---|---|---|---|
| Best possible | TIER1 | 1.0 | ✅ | 7/7 | `1.2 × 1.0 × 1.1 × 2.0 = 2.64` |
| TIER1, no outbound, all sources | TIER1 | 1.0 | ❌ | 7/7 | `1.2 × 1.0 × 1.0 × 2.0 = 2.40` |
| TIER1, outbound, 6 sources | TIER1 | 1.0 | ✅ | 6/7 | `1.2 × 1.0 × 1.1 × 1.936 = 2.5552` |
| TIER1, outbound, 5 sources | TIER1 | 1.0 | ✅ | 5/7 | `1.2 × 1.0 × 1.1 × 1.861 = 2.4574` |
| TIER1, outbound, 1 source | TIER1 | 1.0 | ✅ | 1/7 | `1.2 × 1.0 × 1.1 × 1.333 = 1.76` |

### Geographic Diversity

The final published list applies a cap of **8 peers per geographic region** to prevent any single region from dominating. If fewer than 20 peers are available after the regional cap, the list is filled with the best remaining peers regardless of region.

Regions are derived from GeoLite2-Country continent codes (MaxMind):

| Region code | Coverage |
|---|---|
| `EU` | Europe |
| `NA` | North America |
| `AS` | Asia and Oceania (OC is merged into AS - few Cosmos nodes are located in Oceania) |
| `SA` | South America |
| `OTHER` | Africa, Antarctica, and unresolved IPs |

Geolocation requires the **GeoLite2-Country** database (free with MaxMind registration). Without it, all peers are assigned region `OTHER` and geographic diversity is not applied.

---

## 4. How to Appear on the List

Your node will appear on the Cumulo Live Peers list when it meets these conditions:

### Step 1 - Have your p2p port publicly accessible

The collector performs a **direct TCP probe on your p2p port** (default 26656). Your node must accept incoming connections on this port from external IPs.

> ⚠️ Your RPC port does not need to be open. The collector never touches the RPC of candidate peers.

A reachable port is necessary but, on chains where the handshake probe is active (see the status table in [section 7](#7-p2p-handshake-probe)), no longer enough: your node must also complete the CometBFT handshake and keep free inbound slots (`max_num_inbound_peers`). A real moniker helps too (see [section 7](#7-p2p-handshake-probe)).

Verify your p2p port is reachable:
```bash
# From any external machine:
nc -zv <your_ip> 26656
```

### Step 2 - Be seen by multiple sources

The `source_count_max` field - which drives the `diversity_bonus` - reflects how many independent `/net_info` endpoints (our node + validator RPCs) reported your node **simultaneously in the same cycle**.

To maximize this:
- Ensure your node has `max_num_inbound_peers` set high enough (≥40 recommended)
- Maintain stable, long-running connections rather than frequently reconnecting
- Use a static IP - nodes that change IPs frequently lose their accumulated history

### Step 3 - Be outbound, not just inbound

The `outbound_bonus` rewards peers that are actively chosen by other nodes. To maximize it:
- Keep your node running continuously with good uptime
- Avoid overly restrictive firewall rules that prevent outbound p2p connections
- Use `persistent_peers` to maintain stable connections to well-known nodes

### Step 4 - Maintain uptime over time

The sliding buffer rewards **consistency**, not just current availability. A node that is reachable 7 out of every 10 checks (70% uptime over ~5 hours) qualifies. A node that was perfectly reachable for 5 days but went down recently will gradually drop out of the list as older successful observations leave the buffer.

### What the scoring looks like in practice

If your node is:
- Publicly reachable on p2p ✅
- Seen as outbound by our node and 3 out of 6 validator RPCs (4 sources total out of 7) ✅
- Stable over the last 5 hours ✅

Your score will be approximately:
```
diversity_bonus = 1.0 + log2(4+1) / log2(7+1) = 1.0 + 0.774 = 1.774
score = 1.2 × 1.0 × 1.1 × 1.774 ≈ 2.34
```

---

## 5. Contributing to the Validators JSON

Cumulo maintains its own resource lists for each supported chain - including RPC endpoints, addrbooks, and peer addresses - which are published openly as part of our infrastructure services. These lists are the foundation of the peer discovery system.

Every validator that contributes resources to these lists expands the peer discovery pool and improves the scoring precision for all peers - not just their own.

### The two functions of the validators JSON

**RPC endpoints → TIER1 discovery + scoring signal**

Each validator RPC that responds to `/net_info` provides an independent view of the active network. The union of multiple independent views covers a much larger fraction of the live network than any single node can see. More importantly, a peer appearing simultaneously in multiple independent `/net_info` responses carries a much stronger quality signal than one seen by a single source.

The more RPC endpoints in the JSON, the more granular the `diversity_bonus` discrimination becomes:

| Validators with RPC | Total sources (incl. own node) | Scoring discrimination |
|---|---|---|
| 2-3 | 3-4 | Low - most peers score similarly |
| 5-7 | 6-8 | Medium |
| 10-15 | 11-16 | High - meaningful score gaps between peers |
| 15+ | 16+ | Maximum precision |

**addrbook / peers → TIER2 reserve**

These sources populate the reserve candidate pool. Peers in TIER2 are TCP-probed every cycle and accumulate reachability history in the store, so that when they eventually appear in a `/net_info` and transition to TIER1, they already have a history that accelerates their path to publication.

> **Note on seeds:** Seed node addresses are documented in the validators JSON but are intentionally excluded from the candidate pool. Seed nodes do not maintain persistent p2p connections and are therefore not suitable as `persistent_peers`.

### JSON format

The validators JSON for each chain lives in this repository:

- Cosmos Hub testnet: [`data/validators_testnet.json`](https://github.com/Cumulo-pro/cumulo-cosmoshub-infra/blob/main/data/validators_testnet.json)
- Cosmos Hub mainnet: [`data/validators.json`](https://github.com/Cumulo-pro/cumulo-cosmoshub-infra/blob/main/data/validators.json)

```json
[
  {
    "name": "YourValidator",
    "rpc": "https://cosmos.rpc.yourvalidator.com",
    "addrbook": "https://snapshots.yourvalidator.com/cosmos/addrbook.json",
    "peers": "nodeid1@ip1:port1,nodeid2@ip2:port2",
    "seed": "seedid@seedip:port"
  }
]
```

| Field | Required | Description |
|---|---|---|
| `name` | ✅ | Your validator name (used in logs and documentation) |
| `rpc` | ✅ | Public RPC endpoint. Must respond to `/net_info`. This is the most valuable contribution. |
| `addrbook` | ⬜ | URL to your `addrbook.json`. Contributes TIER2 reserve candidates. |
| `peers` | ⬜ | Comma-separated `nodeid@ip:port` strings. TIER2 p2p candidates. |
| `seed` | ⬜ | Your seed node address. Documented for reference but not used in peer discovery. |

### How to add your validator

1. Fork this repository
2. Edit the relevant validators JSON file for your chain
3. Add your entry following the format above
4. Open a Pull Request

The collector reads the JSON from GitHub on every cycle - no changes needed on our infrastructure. Your validator will start contributing to peer discovery in the next cycle after the PR is merged.

### Requirements for the RPC endpoint

- Must be publicly accessible without authentication
- Must respond to `GET /net_info` with a valid CometBFT response
- Does not need to be your validator's signing node RPC - a full node RPC is fine and recommended
- The collector makes one request per 30-minute cycle, so rate limiting is not a concern

### What you get by contributing

- Your node's peers benefit from the expanded discovery pool
- Your validator is credited as a trusted source in the system logs and documentation
- If your node's p2p port is publicly reachable, it will appear in the peer list with its full score reflecting the complete validator pool
- The ecosystem as a whole benefits from a more accurate and comprehensive peer list

---

## 6. Public API

### Endpoints

```
https://peers.cumulo.me/peers/<chain>/<network>/peers.json
https://peers.cumulo.me/peers/<chain>/<network>/peers.txt
```

**Available chains:**

| Chain | Network | chain_id | Status | Peer list |
|---|---|---|---|---|
| Cosmos Hub | testnet | provider | ✅ Active | TCP probe (handshake probe in observation) |
| Cosmos Hub | mainnet | cosmoshub-4 | ✅ Active | P2P handshake |
| Celestia | mainnet | celestia | ✅ Active | P2P handshake |
| Celestia | testnet | mocha-5 | ✅ Active | P2P handshake |
| XRPL EVM | mainnet | xrplevm_1440000-1 | ✅ Active | P2P handshake |
| XRPL EVM | testnet | xrplevm_1449000-1 | ✅ Active | P2P handshake |
| Gno.land | mainnet | gnoland-1 | ✅ Active | TCP probe (handshake probe in observation) |
| Gno.land | testnet | pearl-1 | ⏸ Not currently updated (last update 2026-09-19) | - |

"P2P handshake" means every published peer completed a real handshake with the collector (see [section 7](#7-p2p-handshake-probe)).
Limonata testnet is no longer published (the network was decommissioned).

**Live endpoints:**  
[Cosmos Hub testnet peers.json](https://peers.cumulo.me/peers/cosmos/testnet/peers.json) · [peers.txt](https://peers.cumulo.me/peers/cosmos/testnet/peers.txt)  
[Cosmos Hub mainnet peers.json](https://peers.cumulo.me/peers/cosmos/mainnet/peers.json) · [peers.txt](https://peers.cumulo.me/peers/cosmos/mainnet/peers.txt)  
[Celestia mainnet peers.json](https://peers.cumulo.me/peers/celestia/mainnet/peers.json) · [peers.txt](https://peers.cumulo.me/peers/celestia/mainnet/peers.txt)  
[Celestia testnet peers.json](https://peers.cumulo.me/peers/celestia/testnet/peers.json) · [peers.txt](https://peers.cumulo.me/peers/celestia/testnet/peers.txt)  
[XRPL EVM mainnet peers.json](https://peers.cumulo.me/peers/xrplevm/mainnet/peers.json) · [peers.txt](https://peers.cumulo.me/peers/xrplevm/mainnet/peers.txt)  
[XRPL EVM testnet peers.json](https://peers.cumulo.me/peers/xrplevm/testnet/peers.json) · [peers.txt](https://peers.cumulo.me/peers/xrplevm/testnet/peers.txt)  
[Gno.land mainnet peers.json](https://peers.cumulo.me/peers/gnoland/mainnet/peers.json) · [peers.txt](https://peers.cumulo.me/peers/gnoland/mainnet/peers.txt)

### peers.txt

Plain text, comma-separated connection strings. Ready to paste into `config.toml`:

```bash
PEERS=$(curl -s https://peers.cumulo.me/peers/cosmos/testnet/peers.txt)
sed -i -e "/^\[p2p\]/,/^\[/{s/^[[:space:]]*persistent_peers *=.*/persistent_peers = \"$PEERS\"/}" \
  $HOME/.gaia/config/config.toml
```

### peers.json

Full structured response with scoring metadata:

```json
{
  "chain_id": "provider",
  "network": "testnet",
  "updated_at": "2026-05-15T05:06:01Z",
  "peer_count": 20,
  "daemon_home": ".gaia",
  "peers": [
    {
      "id": "a3825c8fb9b8fc6e95b10279ccd898321fa37c20",
      "address": "54.37.31.127:14956",
      "connection_string": "a3825c8fb9b8fc6e95b10279ccd898321fa37c20@54.37.31.127:14956",
      "region": "EU",
      "tier": "TIER1",
      "score": 2.64,
      "stability": 1.0,
      "source_count": 7,
      "outbound": true,
      "tcp_verified": true,
      "first_seen": "2026-05-12T05:09:03Z"
    }
  ]
}
```

| Field | Type | Description |
|---|---|---|
| `tier` | string | `TIER1` (active, verified) or `TIER2` (reserve - not currently published) |
| `score` | float | Multi-factor score. Higher = better. Maximum is always 2.64 regardless of chain or validator count. |
| `stability` | float | Fraction of the sliding buffer that is successful. `1.0` = perfect over last 5 hours. |
| `source_count` | int | Number of distinct `/net_info` sources (our node + validator RPCs) that reported this peer simultaneously in the same cycle. |
| `outbound` | bool | Whether this peer has been outbound in the majority of observations. |
| `tcp_verified` | bool | `true` for every published peer: only peers that passed the TCP probe are published (IPv6 peers are not published). |
| `first_seen` | ISO8601 | When this peer was first detected by the collector. |

On chains where the handshake probe is active (see [section 7](#7-p2p-handshake-probe)) each peer also carries:

| Field | Type | Description |
|---|---|---|
| `p2p_status` | string | Result of the last handshake check. `ok` for every published peer on those chains. |
| `handshake_ms` | int or null | Duration of the last handshake, measured from the collector (not from the reader). |
| `moniker` | string or null | Moniker the node announced. Remote text, sanitized (printable characters, 64 max); escape it when rendering HTML. |
| `cometbft_version` | string or null | Software version the node announced (CometBFT, or Tendermint2 on Gno.land). |
| `p2p_checked_at` | ISO8601 or null | When the peer last gave a conclusive handshake result. |

Chains without the probe do not have these fields.

### Response headers

```
Cache-Control: no-cache, max-age=300
Access-Control-Allow-Origin: *
```

The list is refreshed every 30 minutes. Clients should not poll more frequently than every 5 minutes.

---

## 7. P2P Handshake Probe

### Status per chain (2026-11-14)

| Chain | Network | Published list | Handshake probe |
|---|---|---|---|
| Celestia | mainnet | P2P handshake | Active since 2026-11-12 |
| Celestia | mocha-5 | P2P handshake | Active since 2026-11-14 |
| Cosmos Hub | mainnet | P2P handshake | Active since 2026-11-14 |
| XRPL EVM | mainnet | P2P handshake | Active since 2026-11-14 |
| XRPL EVM | testnet | P2P handshake | Active since 2026-11-14 |
| Cosmos Hub | testnet | TCP probe | In observation (nothing published from it yet) |
| Gno.land | mainnet | TCP probe | In observation, Tendermint2 build (nothing published from it yet) |

If the probe cannot run in a cycle, the TCP-based list is published instead (see "Safety net").

### Why

The TCP probe shows that a port is open. It does not show that the node on the other side completes the CometBFT
P2P handshake. In the first measurement on Celestia mainnet (2026-11-11), of the 20 peers published by the
TCP-based list: 4 completed the handshake, 2 completed it and then closed the connection immediately (no free
inbound slots) and 14 accepted the TCP connection but never answered the handshake.

The same comparison on the other chains, over the first 21 hours of observation (2026-11-13 and 14, 44 cycles per
chain, 20 peers in each TCP-based list):

| Chain | Peers of the TCP list that complete the handshake (median of 20) | Handshake-verified list (median size) |
|---|---|---|
| Cosmos Hub mainnet | 4 | 20 |
| Celestia mocha-5 | 13 | 20 |
| XRPL EVM mainnet | 14 | 20 |
| XRPL EVM testnet | 4 | 14 |

XRPL EVM testnet is a small network and most of its peers are connected to our own node (see the shared IP
caveat below), so its verified list is shorter.

### What it does

For each TIER1 IPv4 peer that passed the TCP probe, once per cycle, the probe opens **one** connection and
behaves like a real node up to the point where peers are exchanged:

1. TCP connect (3 s).
2. SecretConnection handshake (X25519 + Ed25519, 5 s). The node ID proven in this step is compared with the
   published one.
3. NodeInfo exchange. The probe presents the chain parameters read from our own node (network, protocol and
   block versions, channels) so that the remote node's compatibility check passes.
4. Holds the connection a few seconds. A peer that closes right after NodeInfo has no free inbound slots.
5. Closes. No retries, no gossip, no messages beyond the handshake.

The probe uses its own dedicated Ed25519 node key (never a validator or node key) and identifies itself with the
moniker `cumulo-peers-probe (peers.cumulo.me)`, so operators can recognize it in their logs.

### Result states

| State | Meaning |
|---|---|
| `ok` | Handshake and NodeInfo completed, connection kept |
| `full` | Handshake and NodeInfo completed, then the peer closed the connection (no free inbound slots) |
| `no_handshake` | TCP open but the handshake was not completed (`secret_conn_timeout`, `reset_early`, `nodeinfo_timeout`, ...) |
| `id_mismatch` | The node ID proven in the handshake differs from the published one |
| `wrong_network` | NodeInfo reports a different chain-id |
| `tcp_closed` | The port does not answer |
| `inconclusive` | No verdict (see below) |
| `skipped` | Not probed within the cycle time budget |

Per peer the collector also keeps the handshake duration, the CometBFT version, the moniker and the last 10
results.

### A measurement caveat: shared IP

When the collector runs on the same public IP as our own node, peers that are connected to that node close the
probe before the handshake (CometBFT rejects a second connection from an IP it is already connected to, and the
default is `allow_duplicate_ip = false`). That looks like `reset_early` but says nothing about the peer. Checked
on 2026-11-11: 11 of 14 such peers completed the handshake when probed from another IP. For this case the
collector records `inconclusive`, which counts neither for nor against the peer. Other failures, notably
`secret_conn_timeout`, were identical from both IPs and are kept as real `no_handshake`. A peer that is always
connected to our node therefore cannot accumulate conclusive results and is not listed.

Tendermint2 (Gno.land) applies the same guard after the NodeInfo exchange, so there it shows up as `full` instead
of `reset_early`; the collector treats it per chain in the same way. Today the collector shares its IP with our own
node on Celestia mainnet, XRPL EVM testnet and Gno.land mainnet.

### What is published (chains where the probe is active)

- A peer is listed only if its latest handshake is `ok`, it has at least 7 successful handshakes in its last 10
  conclusive checks (about 70% over 5 hours), and it does not have 2 failures among its last 3.
- `full` and `no_handshake` peers are not listed. `id_mismatch` and `wrong_network` peers are quarantined (48 h
  and 24 h) before they are probed again.
- Peers on the collector's own IP are never listed.
- Scoring, regional cap and ordering are the same as before; `stability` is computed from the handshake results.
- Each peer carries the extra fields described in section 6.

### Safety net

The collector publishes the TCP-based list instead, and logs why, when the probe did not run or failed in that
cycle, when half or more of the probes were `skipped`, or when the handshake list has fewer than 10 peers. The
service is never left empty because of the probe.

### Not done yet

- Using the handshake duration as an ordering signal (it is recorded and published, not used for ranking).
- Penalizing default monikers (`YOUR_MONIKER`, `node-name`, `Tendermint`, ...).
- A cap per operator or per /24 block: several peers of the same operator can appear in the same list.
- Activating the probe on Cosmos Hub testnet and Gno.land mainnet (both in observation).

### Scope

CometBFT chains use the CometBFT build of the probe. Gno.land (Tendermint2) has its own build with the same states
and output; in a test against 47 real peers of gnoland-1 it gave 6 `ok`, 27 `full` and 14 `tcp_closed`, identical in
two consecutive passes: most of its peers do not accept inbound connections. Each chain opts in separately.

### What operators can do now

Keep free inbound slots, keep the node ID stable, and set a real moniker instead of the default.

---

## 8. Configuration

Everything is configured in one file, `config/config.yaml`. Adding a chain or changing how a chain is probed never
requires a code change. The file has a `global` block with the defaults and a `chains` list with one block per
published network.

### Chain block

| Key | Meaning |
|---|---|
| `name`, `network` | Path of the published files: `/peers/<name>/<network>/`. |
| `chain_id` | Chain-id of the network. The handshake probe rejects peers that report a different one. |
| `rpc_url` | RPC of one of our own nodes. Used for `/net_info` and, with the probe, as the source of the NodeInfo template. |
| `validators_json_url` | Validators JSON with the RPC endpoints of other operators (see section 5). |
| `p2p_default_port` | Port assumed when a source gives none. |
| `daemon_home` | Name of the node home folder, used when reading addrbooks. |
| `enabled` | `false` skips the chain. Its store and published files are kept as they were. |
| `cycle_offset_minutes` | Minute of the half hour at which the chain runs (see "Timing"). |
| `p2p_probe` | Handshake probe settings for this chain (below). Merged over the global block. |

### Handshake probe settings

Set in `global.p2p_probe` (defaults for every chain) and overridden per chain in `chains[].p2p_probe`.

| Key | Default | Meaning |
|---|---|---|
| `enabled` | `false` | Run the probe on this chain. |
| `mode` | `shadow` | `shadow`: record and compare only, the published list does not change. `active`: publish only peers that completed the handshake. |
| `min_peers` | `10` | If the handshake list would have fewer peers than this, the TCP-based list is published for that cycle (see "Safety net"). |
| `exclude_ips` | none | Peers on these IPs are never listed. Set it to the collector's own IP: a handshake against our own machine says nothing about the network. |
| `shared_ip_with_node` | `false` | `true` when one of our own nodes of this chain runs on the collector's public IP (see "A measurement caveat: shared IP"). |
| `shared_ip_signal` | `reset_early` | How the duplicate IP guard of that node software shows up: `reset_early` (CometBFT) or `full` (Tendermint2). Only used when `shared_ip_with_node` is `true`. |
| `own_outbound_ok` | `false` | Only used with `shared_ip_with_node: true`. A peer that our own node dialed and is connected to counts as a successful handshake (`ok`, detail `own_outbound`) when the shared IP guard is the only thing that stopped the probe. Real failures such as `secret_conn_timeout`, `wrong_network` or `tcp_closed` are never overridden. |
| `binary` | global block (`bin/p2pprobe`) | Probe executable. If the file does not exist the probe is skipped with a warning. Gno.land uses the Tendermint2 build, `bin/p2pprobe-tm2`. |
| `template_rpc` | the chain `rpc_url` | RPC whose `/status` provides the NodeInfo template presented by the probe. |
| `key_file` | global block (`data/probe_node_key.json`) | Dedicated Ed25519 node key of the probe. Never the key of a validator or node. Each build has its own file. |
| `concurrency` | `20` | Simultaneous handshakes. |
| `dial_timeout_seconds` | `3` | TCP connect timeout. |
| `handshake_timeout_seconds` | `5` | SecretConnection and NodeInfo timeout. |
| `hold_seconds` | `4` | How long the connection is kept open after NodeInfo to detect peers without free inbound slots. |
| `budget_seconds` | `240` | Total time budget per chain and cycle. Peers not reached in time are recorded as `skipped`. |

### Example: a chain with the probe active

```yaml
  - name: celestia
    network: mainnet
    enabled: true
    chain_id: celestia
    rpc_url: https://celestia.cumulo.org.es
    validators_json_url: https://github.com/Cumulo-pro/Cumulo-Front-Chain/blob/main/Celestia/data/validators.json
    p2p_default_port: 26656
    daemon_home: .celestia-app
    cycle_offset_minutes: 12
    p2p_probe:
      enabled: true
      mode: active
      min_peers: 10
      exclude_ips: ["148.72.141.245"]
      shared_ip_with_node: true
```

### Example: Tendermint2 (Gno.land)

```yaml
    p2p_probe:
      enabled: true
      mode: shadow
      shared_ip_with_node: true
      shared_ip_signal: full
      own_outbound_ok: true
      binary: /home/cumvelia2/cumulo-peers/bin/p2pprobe-tm2
      key_file: /home/cumvelia2/cumulo-peers/data/probe_node_key_tm2.json
```

The Tendermint2 build is compiled against `github.com/gnolang/gno` and speaks the same SecretConnection, but its
NodeInfo is amino encoded and node IDs are bech32 (`g1...`). It takes the same command line flags as the CometBFT
build and produces the same states.

### Enabling the probe on a new chain

1. **Observe.** Set `p2p_probe.enabled: true` and leave `mode` as `shadow`. Nothing published changes. Each cycle the
   collector records the handshake result of every peer in the store and in `data/shadow_<chain_id>.jsonl`.
2. **Wait.** About 24 hours (about 5 hours is the minimum for a peer to fill its 10 results buffer).
3. **Compare.** For the same cycles, count how many peers of the TCP-based list complete the handshake and how many
   peers the handshake list would publish. If the handshake list is regularly shorter than `min_peers`, either the
   chain has few reachable peers or `shared_ip_with_node` is missing.
4. **Activate.** Set `mode: active`. From the next cycle the published list is the handshake list, with the safety net
   behind it.
5. **Check.** The cycle log has one line `Lista publicada` per chain saying which list was published and why.

To go back, set `mode: shadow`. The very next cycle publishes the TCP-based list again; no data is lost because the
handshake results keep being recorded.

A validators JSON that lists endpoints of another network (for example a testnet RPC in a mainnet file) is
detected by the probe: those peers are reported as `wrong_network` and quarantined, and they never reach the list.

### Timing

A systemd timer fires every 6 minutes. A chain runs in the slots where `minute % 30 == cycle_offset_minutes % 30`,
so six chains can share the half hour without overlapping. Two chains may share an offset when both finish well
inside the slot. A run that lasts longer than 6 minutes makes the next slot skip, which is why the probe has its own
`budget_seconds` (240 s) and why chains with many peers should not share an offset.

| Offset | Chains |
|---|---|
| 0 | Cosmos Hub testnet (`provider`) |
| 6 | Cosmos Hub mainnet |
| 12 | Celestia mainnet |
| 18 | Celestia mocha-5 |
| 24 | XRPL EVM mainnet and testnet |
| 30 (acts as 0) | Gno.land mainnet |

### What changes on the dashboard

Pages read `peers.json`. When every listed peer carries `p2p_status: "ok"`, the service page labels the list and its
column as P2P handshake; otherwise it shows the TCP wording. The text of the connection guide of a chain changes only
when that chain publishes the handshake list.

---

## Notes

**Why TCP probe and not RPC probe?**  
Many validators keep their RPC closed for security reasons. Probing the RPC would systematically penalize well-configured nodes. The p2p port is the only port that a functioning node must keep open, making it the right target for reachability verification. The handshake probe of section 7 also connects to the p2p port only.

**Why does the list start small?**  
The sliding buffer requires a minimum of 7 successful observations before a peer is published. With a 30-minute interval, new peers take approximately 3.5 hours to appear. This is intentional - the list reflects verified stability over time, not an instant snapshot.

**Why can a handshake-verified list have fewer than 20 peers?**  
Only peers with enough recent successful handshakes are published, and the list is not padded with peers that fail. On Celestia mainnet it varied between 13 and 20 peers in the first days, and XRPL EVM testnet, a small network, publishes around 13 to 15. If it ever drops below a minimum (10), the TCP-based list is published instead.

**IPv6 support**  
The collector currently lacks outbound IPv6 connectivity. IPv6 peers that are actively connected as outbound on our own node are treated as verified without TCP probe and kept in the store, but they are not published (IPv4 only in `peers.txt` and `peers.json`). Full IPv6 TCP probe support is pending a network upgrade.

**Geographic TCP probe perspective**  
All probes originate from the same network location. A peer reachable from there is very likely reachable globally, but latency characteristics may vary by region. The dashboard sorts peers by geographic proximity to the visitor to partially compensate for this.

**Benchmark**  
Independent validation of the system's reliability across 8 networks (4 mainnets, 4 testnets), comparing TCP connectivity against snapshot and scanner-based peer lists → [PEERS_BENCHMARK.md](./PEERS_BENCHMARK.md)

---

*Maintained by [Cumulo](https://cumulo.pro) - Cosmos Infrastructure*
