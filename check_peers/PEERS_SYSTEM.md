# Cumulo Live Peers - Technical Documentation

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

> *Which peers have been consistently reachable from outside, across multiple independent nodes, over the last several hours?*

Cumulo Live Peers answers that question by:

- Aggregating peers from **multiple independent sources** simultaneously (our node + external validator RPCs + addrbooks)
- **TCP-probing** every IPv4 peer directly on the p2p port - not the RPC - to verify external reachability
- Maintaining a **10-observation sliding buffer** (~5 hours) and only publishing peers that pass a minimum stability threshold
- Applying a **multi-factor score** that rewards peers seen simultaneously across many independent validator nodes
- Refreshing every **30 minutes** automatically

The key differentiator: the more validators Cumulo has in its JSON, the more precise the scoring becomes - automatically, without any code changes. A peer seen simultaneously in the `/net_info` of 10 independent validators carries a much stronger quality signal than one seen by a single node.

---

## 2. Architecture & Data Flow

### Infrastructure

| Component | Location | Role |
|---|---|---|
| Collector | Velia2 (AS30083, St. Louis) | Runs every 30 min via systemd timer |
| Cosmos node | OVH-11 (separate server) | Primary peer source via `/net_info` |
| Web server | Velia2 / Nginx | Serves `peers.cumulo.me` |
| Validators JSON | GitHub (this repo) | List of trusted validator sources |

### Tier System

Every peer candidate is classified into one of two tiers before processing:

**TIER 1 - Active verified peers**
- Source: `/net_info` of our node or external validator RPCs
- The peer is actively connected to at least one known node right now
- IPv4: mandatory TCP probe on the p2p port
- IPv6: included if `is_outbound=true` on our own node (active connection = sufficient proof)
- Buffer weight: `1.0` per successful observation
- Score multiplier: `×1.2`

**TIER 2 - Cold candidates**
- Source: `addrbook.json`, `seed` and `peers` fields from the validators JSON
- Known to other nodes but not verified as currently active
- IPv4: mandatory TCP probe
- IPv6: discarded - no outbound IPv6 from Velia2 currently
- Only the 200 most recent entries from each addrbook (by `last_attempt`) are processed
- Buffer weight: `0.5` per successful observation
- Score multiplier: `×1.0`

> With `threshold=7.0` and `buffer_size=10`, a TIER1 peer needs 7 successful probes to appear on the list. A TIER2 peer would need 14 successes in 10 observations - practically impossible - making TIER2 peers extremely difficult to publish unless genuinely exceptional over many cycles.

### Data Flow

```
GitHub (validators_testnet.json / validators_mainnet.json)
      │
      │  fetched every cycle (raw URL)
      ▼
┌─────────────────────────────────────────────────────────────┐
│                    COLLECTOR (Velia2)                       │
│                                                             │
│  Source 1: GET /net_info  (our node)          → TIER1       │
│            (also counts in source_count_max)                │
│  Source 2: GET /net_info  (validator RPCs)    → TIER1       │
│  Source 3: GET addrbook.json (top 200 recent) → TIER2       │
│  Source 4: seed/peers fields in JSON          → TIER2       │
│          │                                                  │
│          ▼  Deduplication pool                              │
│    - TIER1 is never downgraded to TIER2                     │
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
│          │                                                  │
│          ▼  Multi-factor scoring                            │
│    - tier × stability × outbound × source_diversity         │
│    - diversity scales with validator count in JSON           │
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
| `tier` | `TIER1` or `TIER2`. Never downgraded - can only improve. |
| `ipv6` | `true` if IPv6. These peers skip TCP probe. |
| `buffer_10` | Sliding window of 10 float values: `1.0` TIER1 success, `0.5` TIER2 success, `0.0` failure. |
| `source_count_max` | Maximum number of distinct `/net_info` sources that saw this peer in the same cycle. Core input for the diversity bonus. |
| `outbound_count` | Cycles where this peer was outbound in at least one source. |
| `observations_total` | Total cycles since first detection. |
| `tcp_ok_count` | Total successful TCP probes accumulated. |

---

## 3. Selection Algorithm & Scoring

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

A peer that has been outbound in the majority of observations was actively chosen by multiple nodes - a strong signal of quality.

#### diversity_bonus
```
diversity_bonus = 1.0 + log2(source_count_max + 1) / log2(total_validators + 1)
```

Range: `1.0` (seen in 1 source) → `2.0` (seen in all sources simultaneously)

`source_count_max` is the maximum number of distinct `/net_info` sources that reported this peer **in the same cycle**. This is the most powerful signal in the system:

- A peer seen in 1 out of 4 validator RPCs → diversity_bonus = 1.5
- A peer seen in 4 out of 4 validator RPCs → diversity_bonus = 2.0
- A peer seen in 10 out of 15 validator RPCs → diversity_bonus = 1.93

**This bonus scales automatically with the number of validators in the JSON.** With 4 validators, the discrimination is moderate. With 15+ validators, the difference between a peer seen in 3 sources vs 12 sources becomes very significant.

#### Score examples (with 4 validators in JSON)

| Peer | Tier | Stability | Outbound | Sources | Score |
|---|---|---|---|---|---|
| Best possible | TIER1 | 1.0 | ✅ | 4/4 | `1.2 × 1.0 × 1.1 × 2.0 = 2.64` |
| TIER1, no outbound, all sources | TIER1 | 1.0 | ❌ | 4/4 | `1.2 × 1.0 × 1.0 × 2.0 = 2.40` |
| TIER1, outbound, 3 sources | TIER1 | 1.0 | ✅ | 3/4 | `1.2 × 1.0 × 1.1 × 1.83 = 2.42` |
| TIER1, outbound, 1 source | TIER1 | 1.0 | ✅ | 1/4 | `1.2 × 1.0 × 1.1 × 1.5 = 1.98` |
| TIER2 exceptional | TIER2 | 0.5 | ✅ | 1/4 | `1.0 × 0.5 × 1.1 × 1.5 = 0.83` |

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

The collector performs a **direct TCP probe on your p2p port** (default 26656) from Velia2 (IP: `148.72.141.245`, located in St. Louis, AS30083). Your node must accept incoming connections on this port from external IPs.

> ⚠️ Your RPC port does not need to be open. The collector never touches the RPC of candidate peers.

Verify your p2p port is reachable:
```bash
# From any external machine:
nc -zv <your_ip> 26656
```

### Step 2 - Be seen by multiple sources

The `source_count_max` field - which drives the `diversity_bonus` - is how many independent validator `/net_info` endpoints reported your node **simultaneously in the same cycle**.

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

The sliding buffer rewards **consistency**, not just current availability. A node that is reachable 7 out of every 10 checks (70% uptime) qualifies. A node that was perfectly reachable for 5 days but went down yesterday will gradually drop out of the list.

### What the scoring looks like in practice

If your node is:
- Publicly reachable on p2p ✅
- Seen as outbound by our node and 3 out of 6 validator RPCs (4 sources out of 7 total) ✅
- Stable over the last 5 hours ✅

Your score will be approximately:
```
diversity_bonus = 1.0 + log2(4+1) / log2(7+1) = 1.0 + 0.774 = 1.774
score = 1.2 × 1.0 × 1.1 × 1.774 ≈ 2.34
```
Which places you in the top tier of the published list.

---

## 5. Contributing to the Validators JSON

The validators JSON is the most valuable asset in this system. **Every validator you add improves the scoring precision for everyone automatically.**

### What the JSON does

Each entry in `validators_testnet.json` / `validators_mainnet.json` provides:
- A `/net_info` RPC endpoint → expands TIER1 peer discovery and increases `source_count` for peers visible across multiple validators
- An `addrbook.json` URL → adds TIER2 cold candidates to the pool
- Direct `seed` and `peers` strings → additional TIER2 candidates

The more validators in the JSON, the more granular the `diversity_bonus` discrimination becomes. With 15+ validators, a peer seen in 12/15 sources will score significantly higher than one seen in 3/15 - creating a meaningful quality signal that no other peer provider offers.

### JSON format

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
| `name` | ✅ | Your validator name (used in logs) |
| `rpc` | ✅ | Public RPC endpoint. Must respond to `/net_info`. |
| `addrbook` | ⬜ | URL to your `addrbook.json`. Contributes TIER2 candidates. |
| `peers` | ⬜ | Comma-separated `nodeid@ip:port` strings. TIER2. |
| `seed` | ⬜ | Your seed node address. TIER2. |

### How to add your validator

1. Fork this repository
2. Edit `data/validators_testnet.json` and/or `data/validators_mainnet.json`
3. Add your entry following the format above
4. Open a Pull Request

The collector reads the JSON from GitHub on every cycle - no deployment needed on our side. Your validator will start contributing to peer discovery in the next cycle after the PR is merged.

### Requirements for your RPC

- Must be publicly accessible (no authentication)
- Must respond to `GET /net_info` with a valid CometBFT response
- Does not need to be your validator's main RPC - a separate full node RPC is fine
- Rate limiting is acceptable; the collector makes one request per cycle (every 30 min)

### What you get

By contributing to the validators JSON:
- Your own node's peers benefit from the expanded pool
- Your validator appears in the logs and documentation as a trusted source
- If your node is publicly reachable on p2p, it will appear in the peer list scored against the full validator pool
- The more validators contribute, the better the list quality for the entire ecosystem

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
| Cosmos Hub | mainnet | cosmoshub-4 | 🔜 Pending |
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
  "updated_at": "2026-05-14T15:31:51Z",
  "peer_count": 7,
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
| `tier` | string | `TIER1` (active, verified) or `TIER2` (cold candidate) |
| `score` | float | Multi-factor score. Higher = better. Max ~2.64 with 4 validators. |
| `stability` | float | Fraction of buffer that is successful. `1.0` = perfect. |
| `source_count` | int | Number of distinct validator `/net_info` sources that saw this peer simultaneously. |
| `outbound` | bool | Whether this peer has been outbound in the majority of observations. |
| `tcp_verified` | bool | `false` for IPv6 peers (no direct TCP probe from Velia2). |
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

**Why does the list start small after a new chain is added?**  
The sliding buffer requires a minimum of 7 successful TCP probe observations before a peer is published. With a 30-minute cycle interval, new peers take approximately 3.5 hours to appear on the list. This is intentional - the list reflects verified stability over time, not an instant snapshot of the current network state.

---

*Maintained by [Cumulo](https://cumulo.pro) - Cosmos Infrastructure*
