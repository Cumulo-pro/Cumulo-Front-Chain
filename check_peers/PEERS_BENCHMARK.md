# Peer List Reliability Benchmark

> Real-time TCP connectivity study across 8 networks, validating the Cumulo sliding-window verification model against alternative peer discovery methods.

---

## Methodology

Three peer discovery approaches were benchmarked across 8 blockchain networks (4 mainnets, 4 testnets):

| Approach | Description |
|---|---|
| **Cumulo** | Continuous TCP probes every 30 min. Peers require ≥7/10 reachability score over ~5h before publication. Multi-source cross-validation. Geographic diversity enforced. |
| **Snapshot lists** | Peers scraped from `/net_info` at a single point in time. No stability history. No external TCP verification. |
| **Network scanners** | Tools claiming real-time peer detection. Variable verification depth. Often rely on centralized entry-point domains. |

All probes used direct TCP handshakes (`nc -zv -w3`) from a single external node (France, OVH). Latency measured as wall-clock time from connection open to success/timeout. IPv6 addresses excluded due to probe tool limitations.

**Test date:** May 2026

---

## Results

| Network | Cumulo | Snapshot | Scanner |
|---|:---:|:---:|:---:|
| Cosmos Hub mainnet | ✅ **20/20** | ✅ 10/10 | - |
| Cosmos Hub testnet | ✅ **20/20** | ❌ 8/9 | - |
| Celestia mainnet | ✅ **20/20** | ✅ 11/11 | - |
| Celestia mainnet (large) | ✅ **20/20** | ❌ 66/94 | - |
| Celestia Mocha testnet | ✅ **20/20** | ❌ 31/32 | ❌ 15/18 |
| XRPL EVM mainnet | ✅ **20/20** | ✅ 13/13 | - |
| XRPL EVM mainnet (large) | ✅ **20/20** | ❌ 42/50 | - |
| XRPL EVM testnet | ✅ **20/20** | ❌ 9/10 | ❌ 10/11 |

**Cumulo: 160/160 peers live across all 8 networks. Zero failures.**

---

## Analysis

### 1. Snapshot lists fail at scale

Small snapshot lists (10–13 peers) performed reasonably well - 3 out of 4 achieved 100% connectivity. But larger snapshots (50–137 peers) consistently included a significant fraction of dead peers:

| Snapshot size | Dead peers observed | Failure rate |
|---|---|---|
| 10–13 peers | 1 dead across 3 lists | ~3% |
| 29–50 peers | 4–8 dead per list | 14–16% |
| 94–137 peers | 28–32 dead per list | 23–30% |

The pattern is structural, not coincidental. A snapshot captures whoever a node happened to be connected to at one moment. It has no knowledge of whether those peers were reachable an hour ago, whether they're behind NAT, or whether they've been consistently online. As list size grows, the proportion of transient or unreachable peers grows with it.

The Cumulo sliding window eliminates this: a peer needs to pass TCP probes across ≥7 of the last 10 cycles before appearing in the published list. A peer that was momentarily visible but dropped out after 20 minutes never reaches publication.

### 2. Centralized entry points are a single point of failure

Several snapshot and scanner providers rely on their own domain (e.g. `provider-network-peer.example.net`) as the primary bootstrap node in their published list. Across 7 such entry-point domains tested:

**6 out of 7 were unreachable at test time.**

The one that responded did so at 238ms - the highest latency of any peer in that list. This is not a one-off: the same domains were tested independently across multiple networks at different times, and failed consistently.

Cumulo publishes no centralized entry point. Every peer in the list is an independently verified IP address with a documented stability history.

### 3. Scanner lists do not outperform snapshots

One provider offered both a "live peers" list and a separate "network scanner" list, explicitly described as *"verified for decent uptime in real time."* On the two networks where both were tested:

- The scanner list had **equal or worse** dead-peer rates than the unverified snapshot
- The scanner list included the provider's own domain entry point, which failed on both networks
- On one network, the scanner surfaced 5 peers in the same /24 IP block - appearing fast (16–17ms) but sharing a single datacenter failure domain

Speed is not a substitute for stability history. A peer that responds in 16ms today may be unreachable tomorrow if the datacenter goes offline. Cumulo's geographic diversity cap (max 8 peers per region) explicitly prevents co-location concentration.

### 4. Cumulo consistently finds the fastest peers

Across all 8 networks, Cumulo's list included the fastest or near-fastest peers from any source:

| Network | Cumulo best latency | Best latency from any source |
|---|---|---|
| Cosmos Hub mainnet | 21ms | 21ms (same peer) |
| Cosmos Hub testnet | 14ms | 14ms (same peer) |
| Celestia mainnet | 10ms | 8ms (snapshot excl.) |
| Celestia Mocha testnet | 10ms | 7ms (snapshot excl.) |
| XRPL EVM mainnet | 7ms | 7ms (same peer) |
| XRPL EVM testnet | 17ms | 16ms (scanner excl.) |

The sub-10ms entries in snapshot lists that Cumulo didn't have were consistently exclusive to very large lists (94–137 peers) - nodes that appeared recently or from non-standard vantage points. They represent useful additions when verified as individually live, but they cannot be assumed live without independent probing.

### 5. Cross-source validation confirms the best peers

When a peer appears simultaneously in Cumulo's list and in multiple snapshot sources, it has effectively passed independent verification from multiple vantage points. These cross-validated peers showed the highest latency consistency across sources (differences of 1–3ms between measurements), indicating stable, well-connected nodes.

On Cosmos Hub mainnet, **4 peers appeared in both Cumulo and a 10-peer snapshot** - independent convergence on the same high-quality nodes. On Celestia Mocha, **10 peers appeared in all three sources tested simultaneously**.

---

## Reproduce These Results

The following commands let you run the same TCP probes used in this study. Replace the `PEERS` variable with any peer list you want to test.

### Basic probe - test any peer list

```bash
PEERS="<node_id>@<host>:<port>,<node_id>@<host>:<port>,..."

for p in $(echo "$PEERS" | tr ',' '\n'); do
  h=$(echo $p | cut -d@ -f2 | cut -d: -f1)
  port=$(echo $p | cut -d@ -f2 | cut -d: -f2)
  start=$(date +%s%3N)
  result=$(nc -zv -w3 "$h" "$port" 2>&1)
  end=$(date +%s%3N)
  ms=$((end - start))
  if echo "$result" | grep -qE "succeeded|open|Connected"; then
    echo "✅ $h:$port  ${ms}ms"
  else
    echo "❌ $h:$port  FAIL"
  fi
done
```

### Compare two lists side by side

```bash
probe_list() {
  local label=$1
  local peers=$2
  local ok=0 fail=0 total_ms=0

  for p in $(echo "$peers" | tr ',' '\n'); do
    h=$(echo $p | cut -d@ -f2 | cut -d: -f1)
    port=$(echo $p | cut -d@ -f2 | cut -d: -f2)
    start=$(date +%s%3N)
    result=$(nc -zv -w3 "$h" "$port" 2>&1)
    ms=$(( $(date +%s%3N) - start ))
    if echo "$result" | grep -qE "succeeded|open|Connected"; then
      echo "✅ [$label] $h:$port  ${ms}ms"
      ok=$((ok+1)); total_ms=$((total_ms+ms))
    else
      echo "❌ [$label] $h:$port  FAIL"
      fail=$((fail+1))
    fi
  done

  total=$((ok+fail))
  avg=$([ $ok -gt 0 ] && echo "$((total_ms/ok))" || echo "-")
  echo ""
  echo "[$label] $ok/$total live - avg ${avg}ms"
  echo ""
}

LIST_A="peer1@host1:port,peer2@host2:port"
LIST_B="peer3@host3:port,peer4@host4:port"

probe_list "LIST_A" "$LIST_A"
probe_list "LIST_B" "$LIST_B"
```

### Check if a provider's /net_info is accessible

```bash
# Returns number of connected peers visible via RPC
curl -s https://your-rpc-endpoint.example.com/net_info | jq '.result.n_peers'

# Preview first 3 peer IDs
curl -s https://your-rpc-endpoint.example.com/net_info | \
  jq '[.result.peers[0:3][].node_info.id]'
```

### Detect co-located peers (same /24 block)

```bash
PEERS="..."

echo "$PEERS" | tr ',' '\n' | while read p; do
  echo $p | cut -d@ -f2 | cut -d: -f1
done | sort | awk -F. '{print $1"."$2"."$3".0/24"}' | sort | uniq -c | sort -rn | \
awk '$1 > 1 {print "⚠️  " $1 " peers in " $2}'
```

This prints any /24 blocks with more than one peer - useful for identifying co-location risk before using a list in production.

---

## Example: Cosmos Hub Mainnet

Cumulo published list for Cosmos Hub mainnet at time of writing:

```bash
PEERS="7b15dce221b13ca353187b4f7219a94db6b71ad3@185.119.118.109:2000,27ad834c62dbefc5beb74be7575515927bd07c58@37.120.245.50:26656,793a5c79d2eae09b11c5feed5e945c30f3ccc706@64.130.55.5:26656,36ad7bacc3a18b4deb647c60a0c1d8bbd24fde39@82.113.25.131:26656,3d425652dae7649d4c1b34c5d91435a52b3cc73c@37.120.245.88:26656,9e2e99c6f571e780221a477c9257af099885013f@146.70.243.150:26656,72829b78b38408b03793ed389b9f16596b82c306@146.59.81.92:26656,bd2b5b30ee1a6f3d983bb3c1a083ea37aff18ce1@18.142.7.52:26656,48c5af84afc9e25f62a7189f0260fd907aac5f68@204.16.247.246:26656,620c1ff08988ac2a1014f0964c794cc0a9698899@204.16.247.238:26656,63f1915e9d052a04cb11243bb90ff67879dd972c@141.98.219.28:26656,d9e1182c592a286d16e492a61c4026c79254c7ba@190.2.143.61:26656,8220e8029929413afff48dccc6a263e9ac0c3e5e@204.16.247.237:26656,1c40be406f1fbf6ce82b4bfbe15a3e1b795741d2@67.209.54.237:26656,1b4ca6762c93f7c951d13d8dc1f09a85a6faaa4b@42.200.77.5:11456,eb644d5ede024ce6083c0f1ca038eb41b257b795@3.210.252.30:26656,f9fd30519c915ef1aeb63e99e345f83f08ec69d9@3.208.33.221:26656,3fdd286a90ce8d2ddc6f52f73a286b2364812fd8@169.155.171.230:26656,023eabdd8c577532d54eb4fdafe84e84e08e538f@67.209.54.175:26656,f05ddce65f1e75babe01d05fef1bce5d8ffe0972@54.177.181.170:26656"

for p in $(echo "$PEERS" | tr ',' '\n'); do
  h=$(echo $p | cut -d@ -f2 | cut -d: -f1)
  port=$(echo $p | cut -d@ -f2 | cut -d: -f2)
  start=$(date +%s%3N)
  result=$(nc -zv -w3 "$h" "$port" 2>&1)
  ms=$(( $(date +%s%3N) - start ))
  if echo "$result" | grep -qE "succeeded|open|Connected"; then
    echo "✅ $h:$port  ${ms}ms"
  else
    echo "❌ $h:$port  FAIL"
  fi
done
```

Expected output: 20/20 ✅, all peers alive, latency range 21–224ms depending on your node location.

You can fetch the always-current version of this list and probe it in one command:

```bash
curl -s https://peers.cumulo.me/peers/cosmos/mainnet/peers.txt | \
  tr ',' '\n' | while read p; do
    h=$(echo $p | cut -d@ -f2 | cut -d: -f1)
    port=$(echo $p | cut -d@ -f2 | cut -d: -f2)
    start=$(date +%s%3N)
    result=$(nc -zv -w3 "$h" "$port" 2>&1)
    ms=$(( $(date +%s%3N) - start ))
    if echo "$result" | grep -qE "succeeded|open|Connected"; then
      echo "✅ $h:$port  ${ms}ms"
    else
      echo "❌ $h:$port  FAIL"
    fi
  done
```

---

## Related

- [PEERS_SYSTEM.md](./PEERS_SYSTEM.md) - Full technical specification of the Cumulo peer verification system

