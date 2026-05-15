# Cúmulo Live Peers. Technical Documentation

> A curated, scored, and continuously verified peer list for Cosmos (CometBFT) chains.  
> Live at: **[peers.cumulo.me](https://peers.cumulo.me)**

---

## Table of Contents

1. [Why This Exists](#1-why-this-exists)
2. [Architecture & Data Flow](#2-architecture--data-flow)
3. [Selection Algorithm & Scoring](#3-selection-algorithm--scoring)
4. [How to Appear on the List](#4-how-to-appear-on-the-list)
5. [Contributing to the Validators JSON](#5-contributing-to-the-validators-json)
6. [Public API](#6-public-api)

---

## 1. Why This Exists

Most peer lists in the Cosmos ecosystem are either static (they go stale within hours) or simple snapshots of what a single node sees at a given moment via `/net_info`. Neither approach answers the question that actually matters to a node operator with connectivity problems:

> *Which peers have been consistently reachable from outside, across multiple independent sources, over the last several hours?*

Cúmulo Live Peers goes further by combining four distinct data sources per chain:

- **Our own node's `/net_info`** — the peers our node is actively connected to right now
- **`/net_info` of trusted validator RPCs** — the peers seen by other independent nodes in the ecosystem simultaneously, aggregated from the [validators JSON](https://github.com/Cumulo-pro/cumulo-cosmoshub-infra/tree/main/data)
- **`addrbook.json` from those same validators** — a pool of known peer candidates used as a reserve for future discovery
- **Seed and peer strings from the validators JSON** — additional candidates contributed directly by validators

This multi-source approach means that a peer appearing in the `/net_info` of several independent validators simultaneously carries a much stronger signal than one seen only by our node. The more validators contribute an RPC to the system, the more precise this signal becomes.

Beyond discovery, every IPv4 peer is **TCP-probed directly on the p2p port** to verify external reachability, a **10-observation sliding buffer** (~5 hours) filters out unstable peers, and a **multi-factor score** determines the final ranking.

---

## 2. Architecture & Data Flow

### Infrastructure

| Component | Role |
|---|---|
| Collector | Runs every 30 min via systemd timer, aggregates all sources and publishes results |
| Cosmos node | Primary peer source via `/net_info` |
| Web server / Nginx | Serves `peers.cumulo.me` |
| Validators JSON | Community-maintained list of trusted validator sources on GitHub |

### Tier System

Every peer candidate is classified into one of two tiers before processing:

**TIER 1 — Active verified peers**
- Source: `/net_info` of our node or external validator RPCs
- The peer is actively connected to at least one known node right now
- IPv4: mandatory TCP probe on the p2p port
- IPv6: included if `is_outbound=true` on our own node (active connection = sufficient proof)
- Buffer weight: `1.0` per successful observation
- Score multiplier: `×1.2`

**TIER 2 — Reserve candidates**
- Source: `addrbook.json`, `seed` and `peers` fields from the validators JSON
- Known to other nodes historically but not verified as currently active
- IPv4: TCP-probed to build a reachability history in the store
- IPv6: discarded — no outbound IPv6 connectivity from the collector currently
- Only the 200 most recent entries from each addrbook (by `last_attempt`) are processed
- Buffer weight: `0.5` per successful observation
- Score multiplier: `×1.0`

**The role of TIER 2:**  
With a `buffer_size=10` and `inclusion_threshold=7.0`, the maximum sum a TIER2 peer can ever accumulate is `10 × 0.5 = 5.0` — which never reaches the threshold. TIER2 peers are therefore **never directly published**. Their purpose is to build a pre-vetted reserve: TCP reachability history and store presence are already accumulated by the time a peer transitions to TIER1. This transition happens automatically when the peer appears in any `/net_info` source — at that point it is reclassified as TIER1 and begins accumulating full-weight observations toward the publication threshold.

This design means the definitive proof of peer quality is appearing in a live `/net_info` — which is precisely why having more validator RPCs in the system improves overall list quality.

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
│  Source 2: GET /net_info  (validator RPCs)    → TIER1       │
│  Source 3: GET addrbook.json (top 200 recent) → TIER2       │
│  Source 4: seed/peers fields in JSON          → TIER2       │
│          │                                                  │
│          ▼  Deduplication pool                              │
│    - TIER1 is never downgraded to TIER2                     │
│    - source_count incremented per net_info appearance       │
│    - is_outbound accumulated with OR                        │
│          │                                                  │
│          ▼  Hard filters                                     │
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
      "source_count_max":     4,
      "buffer_10":            [1.0, 1.0, 1.0, 1.0, 1.0, 1.0, 1.0, 1.0, 1.0, 1.0]
    }
  }
}
```

| Field | Description |
|---|---|
| `tier` | `TIER1` or `TIER2`. Never downgraded — can only improve. |
| `ipv6` | `true` if IPv6. These peers skip TCP probe. |
| `buffer_10` | Sliding window of 10 float values: `1.0` TIER1 success, `0.5` TIER2 success, `0.0` failure. |
| `source_count_max` | Maximum number of distinct `/net_info` sources that reported this peer in the same cycle. Core input for the diversity bonus. |
| `outbound_count` | Cycles where this peer was outbound in at least one source. |
| `observations_total` | Total cycles since first detection. |
| `tcp_ok_count` | Total successful TCP probes accumulated. |

---

## 3. Selection Algorithm & Scoring

### Layer 1 — Sliding Window Filter (entry criterion)

A peer is eligible for publication only when the sum of its `buffer_10` reaches the configured threshold:

```
sum(buffer_10) >= inclusion_threshold  (default: 7.0)
```

| Scenario | Buffer values | Sum | Published? |
|---|---|---|---|
| TIER1, 10/10 successful probes | [1.0 × 10] | 10.0 | ✅ Yes |
| TIER1, 7/10 successful probes | [1.0 × 7, 0.0 × 3] | 7.0 | ✅ Yes |
| TIER1, 6/10 successful probes | [1.0 × 6, 0.0 × 4] | 6.0 | ❌ No |
| TIER2, 10/10 successful probes | [0.5 × 10] | 5.0 | ❌ No (max possible is 5.0) |

A peer is **removed** from the published list when its buffer sum drops below the threshold after an update.  
A peer is **purged** from the store entirely if it has not appeared in any source for 5 days.

### Layer 2 — Multi-Factor Score (ordering criterion)

All peers that pass the sliding window are scored and ranked:

```
score = tier_bonus × stability × outbound_bonus × diversity_bonus
```

#### tier_bonus
```
TIER1 → 1.2
TIER2 → 1.0
```

Active verified peers have a structural advantage over cold candidates.

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

A peer that has been outbound in the majority of observations was actively chosen by multiple nodes — a meaningful signal of reliability.

#### diversity_bonus
```
diversity_bonus = 1.0 + log2(source_count_max + 1) / log2(total_validators + 1)
```

Range: `1.0` (seen in 1 source) → `2.0` (seen in all sources simultaneously)

`source_count_max` is the maximum number of distinct `/net_info` sources — both our own node and validator RPCs — that reported this peer **in the same cycle**. This is the most informative signal in the system:

- A peer seen in 1 out of 4 validator RPCs → diversity_bonus ≈ 1.5
- A peer seen in 4 out of 4 validator RPCs → diversity_bonus = 2.0
- A peer seen in 10 out of 15 validator RPCs → diversity_bonus ≈ 1.93

**This bonus scales automatically with the number of RPCs in the validators JSON.** With 4 validators, the scoring differences are moderate. With 15+ validators, the gap between a peer seen in 3 sources vs 12 sources becomes highly significant and provides meaningful discrimination.

This is also why contributing an RPC to the validators JSON directly benefits the entire ecosystem: every additional independent `/net_info` source improves the granularity of the signal for all peers.

#### Score examples (with 4 validators in JSON)

| Peer | Tier | Stability | Outbound | Sources | Score |
|---|---|---|---|---|---|
| Best possible | TIER1 | 1.0 | ✅ | 4/4 | `1.2 × 1.0 × 1.1 × 2.0 = 2.64` |
| TIER1, no outbound, all sources | TIER1 | 1.0 | ❌ | 4/4 | `1.2 × 1.0 × 1.0 × 2.0 = 2.40` |
| TIER1, outbound, 3 sources | TIER1 | 1.0 | ✅ | 3/4 | `1.2 × 1.0 × 1.1 × 1.83 = 2.42` |
| TIER1, outbound, 1 source | TIER1 | 1.0 | ✅ | 1/4 | `1.2 × 1.0 × 1.1 × 1.5 = 1.98` |

### Geographic Diversity

The final published list applies a cap of **8 peers per geographic region** (EU, NA, AS, SA, OTHER) to prevent any single region from dominating. If fewer than 20 peers are available after the regional cap, the list is filled with the best remaining peers regardless of region.

Geolocation is performed via **GeoLite2-Country** (MaxMind). Without this database, all peers are assigned region `OTHER`.

---

## 4. How to Appear on the List

Your node will appear on the Cúmulo Live Peers list when it meets these conditions:

### Step 1 — Have your p2p port publicly accessible

The collector performs a **direct TCP probe on your p2p port** (default 26656). Your node must accept incoming connections on this port from external IPs.

> ⚠️ Your RPC port does not need to be open. The collector never touches the RPC of candidate peers.

Verify your p2p port is reachable:
```bash
# From any external machine:
nc -zv <your_ip> 26656
```

### Step 2 — Be seen by multiple sources

The `source_count_max` field — which drives the `diversity_bonus` — reflects how many independent `/net_info` endpoints (our node + validator RPCs) reported your node **simultaneously in the same cycle**.

To maximize this:
- Ensure your node has `max_num_inbound_peers` set high enough (≥40 recommended)
- Maintain stable, long-running connections rather than frequently reconnecting
- Use a static IP — nodes that change IPs frequently lose their accumulated history

### Step 3 — Be outbound, not just inbound

The `outbound_bonus` rewards peers that are actively chosen by other nodes. To maximize it:
- Keep your node running continuously with good uptime
- Avoid overly restrictive firewall rules that prevent outbound p2p connections
- Use `persistent_peers` to maintain stable connections to well-known nodes

### Step 4 — Maintain uptime over time

The sliding buffer rewards **consistency**, not just current availability. A node that is reachable 7 out of every 10 checks (70% uptime over ~5 hours) qualifies. A node that was perfectly reachable for 5 days but went down recently will gradually drop out of the list as older successful observations leave the buffer.

### What the scoring looks like in practice

If your node is:
- Publicly reachable on p2p ✅
- Seen as outbound by our node and 3 validator RPCs ✅
- Stable over the last 5 hours ✅

Your score will be approximately:
```
1.2 × 1.0 × 1.1 × 1.83 = 2.42
```

---

## 5. Contributing to the Validators JSON

The validators JSON is the foundation of this system. Every validator that contributes an RPC endpoint expands the peer discovery pool and improves the scoring precision for all peers — not just their own.

### The two functions of the validators JSON

**RPC endpoints → TIER1 discovery + scoring signal**

Each validator RPC that responds to `/net_info` provides an independent view of the active network. The union of multiple independent views covers a much larger fraction of the live network than any single node can see. More importantly, a peer appearing simultaneously in multiple independent `/net_info` responses carries a much stronger quality signal than one seen by a single source.

The more RPC endpoints in the JSON, the more granular the `diversity_bonus` discrimination becomes:

| Validators with RPC | Active peers covered | Scoring discrimination |
|---|---|---|
| 2-3 | ~200-300 peers | Low — most peers score similarly |
| 5-7 | ~400-500 peers | Medium |
| 10-15 | ~700-900 peers | High — meaningful score gaps between peers |
| 15+ | Near-complete network view | Maximum precision |

**addrbook / seeds / peers → TIER2 reserve**

These sources populate the reserve candidate pool. Peers in TIER2 are TCP-probed every cycle and accumulate reachability history in the store, so that when they eventually appear in a `/net_info` and transition to TIER1, they already have a history that accelerates their path to publication.

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
| `peers` | ⬜ | Comma-separated `nodeid@ip:port` strings. TIER2. |
| `seed` | ⬜ | Your seed node address. TIER2. |

### How to add your validator

1. Fork this repository
2. Edit the relevant validators JSON file for your chain
3. Add your entry following the format above
4. Open a Pull Request

The collector reads the JSON from GitHub on every cycle — no changes needed on our infrastructure. Your validator will start contributing to peer discovery in the next cycle after the PR is merged.

### Requirements for the RPC endpoint

- Must be publicly accessible without authentication
- Must respond to `GET /net_info` with a valid CometBFT response
- Does not need to be your validator's signing node RPC — a full node RPC is fine and recommended
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

| Chain | Network | chain_id | Status |
|---|---|---|---|
| Cosmos Hub | testnet | provider | ✅ Active |
| Cosmos Hub | mainnet | cosmoshub-4 | ✅ Active |
| Osmosis | mainnet | osmosis-1 | 🔜 Pending |
| Celestia | mainnet | celestia | 🔜 Pending |
| Neutron | mainnet | neutron-1 | 🔜 Pending |

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
      "ipv6": false,
      "score": 2.64,
      "stability": 1.0,
      "source_count": 4,
      "outbound": true,
      "tcp_verified": true,
      "first_seen": "2026-05-12T05:09:03Z"
    }
  ]
}
```

| Field | Type | Description |
|---|---|---|
| `tier` | string | `TIER1` (active, verified) or `TIER2` (reserve candidate — not currently published) |
| `score` | float | Multi-factor score. Higher = better. Max ~2.64 with 4 validator RPCs. |
| `stability` | float | Fraction of the sliding buffer that is successful. `1.0` = perfect over last 5 hours. |
| `source_count` | int | Number of distinct `/net_info` sources that reported this peer simultaneously in the same cycle. |
| `outbound` | bool | Whether this peer has been outbound in the majority of observations. |
| `tcp_verified` | bool | `false` for IPv6 peers (no direct TCP probe available currently). |
| `ipv6` | bool | Whether the peer address is IPv6. |
| `first_seen` | ISO8601 | When this peer was first detected by the collector. |

### Response headers

```
Cache-Control: no-cache, max-age=300
Access-Control-Allow-Origin: *
```

The list is refreshed every 30 minutes. Clients should not poll more frequently than every 5 minutes.

---

## Notes

**Why TCP probe and not RPC probe?**  
Many validators keep their RPC closed for security reasons. Probing the RPC would systematically penalize well-configured nodes. The p2p port is the only port that a functioning node must keep open, making it the right target for reachability verification.

**Why does the list start small?**  
The sliding buffer requires a minimum of 7 successful observations before a peer is published. With a 30-minute interval, new peers take approximately 3.5 hours to appear. This is intentional — the list reflects verified stability over time, not an instant snapshot.

**IPv6 support**  
The collector currently lacks outbound IPv6 connectivity. IPv6 peers that are actively connected as outbound on our own node are included without TCP probe — the active connection is taken as sufficient proof of reachability. Full IPv6 TCP probe support is pending a network upgrade.

**Geographic TCP probe perspective**  
All probes originate from the same network location. A peer reachable from there is very likely reachable globally, but latency characteristics may vary by region. The dashboard sorts peers by geographic proximity to the visitor to partially compensate for this.

---

*Maintained by [Cúmulo](https://cumulo.pro) — Cosmos Infrastructure*
