# 🛰️ check_d : Decentralized Endpoint Monitoring Tool

**check_d** is a decentralized monitoring system designed to evaluate the health, availability, synchronization, and performance of public blockchain endpoints. Unlike traditional tools, it performs **real protocol-level queries** (JSON-RPC, gRPC, REST) from **multiple geographic locations**, generating reproducible metrics for infrastructure observability.

---

## 🚀 Why This Tool?

In a growing modular blockchain ecosystem, access to reliable public nodes is critical for:

- Block explorers
- Indexers and index services
- Cross-chain bridges
- Decentralized applications (dApps)
- Staking infrastructure and delegators

Yet most existing endpoint checkers are **centralized**, based on pings or TCP checks, and **fail to reflect real-world performance**. They also lack transparency, regional insights, and fair comparison models.

**check_d** was built to address these limitations by offering a decentralized, extensible, and transparent monitoring system that:

- Executes **real protocol-level queries** (`GET /status`, `eth_blockNumber`, `/cosmos/auth/v1beta1/bech32`)
- Measures **latency from multiple regions** (🇺🇸 US, 🇪🇺 EU, 🇨🇦 CA)
- Collects metrics such as **average latency, block height, reliability**, and sync status
- Exposes structured **JSON APIs** for programmatic access
- Provides per-network **dashboards** for live comparison and exploration

---

## 📊 What Does It Measure?

Each endpoint is tested using a **real application-level request** depending on its protocol:

| Protocol | Query Used | Checker |
|---|---|---|
| Tendermint / Cosmos SDK RPC | `GET /status` | `server-rpc.js` |
| Cosmos SDK REST API | `GET /cosmos/auth/v1beta1/bech32` | `server-api.js` |
| EVM-compatible JSON-RPC | `eth_blockNumber` | `server-evm.js` |
| gRPC | `grpc.health.v1.Health/Check` | - |

> ⚠️ **RPC checker:** Uses `GET /status` (HTTP REST) rather than `POST /` JSON-RPC. This avoids false negatives on nodes whose reverse proxy only exposes the REST interface.

> ⚠️ **API checker:** Uses `GET /cosmos/auth/v1beta1/bech32` - a lightweight standard Cosmos SDK endpoint that returns the chain's bech32 prefix. No browser or Puppeteer needed - plain `fetch` is sufficient.

---

## 🌐 Decentralized Architecture

The system runs three independent checker pipelines in parallel:

### RPC Checker Pipeline
```
[ US Agent - server-rpc.js ]  ─┐
[ EU Agent - server-rpc.js ]  ─┼──▶  aggregator.js  ──▶  /aggregate-rpcs  ──▶  Dashboards
[ CA Agent - server-rpc.js ]  ─┘
```

### REST API Checker Pipeline
```
[ US Agent - server-api.js ]  ─┐
[ EU Agent - server-api.js ]  ─┼──▶  aggregator-api.js  ──▶  /aggregate-apis  ──▶  Dashboards
[ CA Agent - server-api.js ]  ─┘
```

### EVM JSON-RPC Checker Pipeline
```
[ US Agent - server-evm.js ]  ─┐
[ EU Agent - server-evm.js ]  ─┼──▶  aggregator-evm.js  ──▶  /aggregate-evm  ──▶  Dashboards
[ CA Agent - server-evm.js ]  ─┘
```

### Current Infrastructure

| Role | Region |
|---|---|
| RPC Checker + API Checker + EVM Checker | 🇺🇸 St. Louis, US |
| RPC Checker + API Checker + EVM Checker | 🇪🇺 France, EU |
| RPC Checker + API Checker + EVM Checker | 🇨🇦 Canada, CA |
| RPC Aggregator + API Aggregator + EVM Aggregator | 🇺🇸 St. Louis, US |

---

## ✅ Design Guarantees

| Feature | Description |
|---|---|
| Real protocol calls | Uses `GET /status`, `eth_blockNumber`, or `/cosmos/auth/v1beta1/bech32` - not pings or TCP |
| Multi-region probes | Simulates real-world user experience from US, EU, and CA |
| Uniform logic | All nodes in a network are tested using the exact same rules |
| Transparent data | All endpoints, scripts, and configurations are open and auditable |
| Extensible design | Easily supports new chains and protocols via `chains.json` config |
| Public results | JSON API and web dashboards are open to the public, no paywalls |
| Anti-overlap protection | Each agent skips a new cycle if the previous one is still running |
| Aggregator cache | Both aggregators cache results for 5 minutes, responding instantly |
| No browser dependency | REST API checker uses plain `fetch` - no Puppeteer or Chromium required |
| Per-endpoint freshness | `checkedAt` and `lastSuccessAt` timestamps on every entry, so staleness is visible per row, not just assumed from the refresh interval |
| Anti-flapping latency | A smoothed EMA latency (`smoothedAverageLatency`) sits alongside the raw per-cycle value, safe to use for automatic endpoint selection without one-off spikes causing switches |
| Curated pruning data | Archival vs pruned is read from validator-declared data (`archive_rpc`), not guessed from block heights - see [Pruning / Archival Classification](#-pruning--archival-classification) below |
| Independent TLS monitoring | Certificate validity and expiry are checked on their own hourly cycle, so they're still visible for endpoints that are otherwise down |
| Passive rate-limit signal | `X-RateLimit-*` / `Retry-After` headers are surfaced when a server sends them, with zero extra requests |
| Public ranking API | `/best-rpc`, `/rank-rpcs`, `/best-api`, `/rank-apis` return the current best endpoint(s) as JSON, filterable by region - see [Smart Endpoint Selector](#-smart-endpoint-selector) below |

---

## 🔧 Improvements Over Traditional Checkers

| Aspect | Legacy Tools | **check_d** |
|---|---|---|
| Real latency | ❌ No (uses ping/TCP) | ✅ Yes, real protocol calls |
| Decentralized agents | ❌ No | ✅ Yes, globally distributed |
| Cross-chain support | ❌ Limited | ✅ Yes (EVM, Cosmos RPC, Cosmos REST, gRPC) |
| Extensible | ❌ Closed | ✅ Open-source and modular |
| Validator comparison | ❌ Not supported | ✅ Yes, per-provider latency and reliability |
| Detailed metrics | ❌ Minimal | ✅ Full latency, sync, block height, reliability |
| False negatives | ❌ Common (POST JSON-RPC, browser issues) | ✅ Avoided via `GET /status` and plain fetch |

---

## 📈 Who Is It For?

- Validator operators comparing the quality of their RPCs and APIs against others
- Protocol teams monitoring public infrastructure
- Delegators choosing high-quality validators
- Indexers and explorers selecting fast and reliable endpoints
- dApp developers seeking the best RPC or API provider per region

---

## 🔁 How It Works (Step by Step)

### RPC Checker (`server-rpc.js`)

1. Reads [`chains.json`](https://raw.githubusercontent.com/Cumulo-pro/Cumulo-Front-Chain/refs/heads/main/chains.json) to get validator lists per chain
2. For each validator with an `rpc` field, sends `GET {rpc}/status` with an `Origin: https://cumulo.pro` header (needed for an accurate CORS check, see below)
3. Parses `sync_info` and `node_info` from the response
4. Records latency, block height, moniker, version, tx_index
5. Reads `Access-Control-Allow-Origin` from the response to set `corsEnabled`
6. Reads `X-RateLimit-*` / `Retry-After` if present to set `rateLimitHint` (passive only, no extra requests)
7. Classifies `pruning` from the validator's curated `archive_rpc` field (see [Pruning / Archival Classification](#-pruning--archival-classification)), independent of the `/status` response
8. Reads `tls` from an hourly-refreshed cache keyed by hostname (see [TLS Certificate Monitoring](#-tls-certificate-monitoring))
9. Stamps `checkedAt` (this cycle) and updates persisted `lastSuccessAt` / smoothed latency in `rpc-meta.json`
10. Saves a reliability history entry to `reliability.json`
11. Serves results at `:3003/check-rpcs`

### REST API Checker (`server-api.js`)

1. Reads the same [`chains.json`](https://raw.githubusercontent.com/Cumulo-pro/Cumulo-Front-Chain/refs/heads/main/chains.json)
2. For each validator with an `api` field, sends `GET {api}/cosmos/auth/v1beta1/bech32` with an `Origin: https://cumulo.pro` header
3. Checks the response for a valid `bech32_prefix` field
4. Records latency and working/error status
5. Reads `Access-Control-Allow-Origin`, `X-RateLimit-*` / `Retry-After`, and the hourly TLS cache, exactly as the RPC checker does (no `pruning` field, not applicable to REST)
6. Stamps `checkedAt` and updates persisted `lastSuccessAt` / smoothed latency in `api-meta.json`
7. Saves a reliability history entry to `reliability_apis.json`
8. Serves results at `:3005/check-apis` (or `:3006` if port is occupied)

Both RPC and API checkers run every **5 minutes** with anti-overlap protection. TLS checks run on their own **1 hour** cycle, chained to run right after the first full RPC/API cycle completes on startup (not a fixed delay, so it never fires against an empty endpoint list after a restart).

### EVM JSON-RPC Checker (`server-evm.js`)

1. Reads the same [`chains.json`](https://raw.githubusercontent.com/Cumulo-pro/Cumulo-Front-Chain/refs/heads/main/chains.json)
2. For each validator with an `evm` field, sends `POST {evm}` with body `{"method":"eth_blockNumber",...}`
3. Parses the hex block number from the response (`data.result`)
4. Records latency (null on error) and reliability history to `evm-reliability.json`
5. **Persists results to disk** (`data/check-evm.json`) - survives restarts with warm cache
6. Serves results at `:3004/check-evm`

> ⚠️ The EVM checker refreshes every **1 hour** (not 5 minutes) - EVM block times are much faster and the checker is more resource-intensive due to Keep-Alive connection pooling across many chains.

---

## 📦 Pruning / Archival Classification

Early versions inferred `pruning` from the RPC's own `sync_info.earliest_block_height`: `<= 1` meant archival, anything else meant pruned. Live testing against real Cosmos Hub endpoints showed this heuristic is unreliable on any long-running chain:

- Most RPCs simply return `earliest_block_height: "0"`, which isn't even a valid block height (blocks are 1-indexed) - it's a placeholder some nodes never update, most often after a state-sync bootstrap.
- Even the endpoint a validator operator explicitly labels as their **archive** node doesn't necessarily serve from genesis on a chain running since 2019 - it returned an `earliest_block_height` in the millions, not `1`.

Since no block-height threshold reliably separates pruned from archival, `pruning` is now classified purely from **curated, operator-declared data**: if a validator's entry in the chain's `validators.json` includes a populated `archive_rpc` field, its main `rpc` entry is marked `pruned` (the operator explicitly runs a separate archive endpoint, so the main one isn't it). Without that field, `pruning` is `pruned` by default rather than guessed - this matches reality for the vast majority of public RPCs, which don't retain full history.

```json
{
  "name": "ValidatorName",
  "rpc": "https://rpc.example.com",
  "archive_rpc": "https://rpc-archive.example.com"
}
```

`archive_rpc` isn't checked or listed as its own endpoint yet - it's read purely as a signal for classifying the main `rpc` entry. Not applicable to the REST API checker.

---

## 🔐 TLS Certificate Monitoring

Each checker independently verifies the TLS certificate of every known host, using Node's `tls` module directly (`rejectUnauthorized: false`, so it can still read and report on invalid certificates rather than failing outright) instead of relying on the pass/fail behavior of the regular `fetch` request:

- `valid`: whether `socket.authorized` came back true (real certificate-chain validation, not just "did it connect")
- `daysUntilExpiry`: computed from the certificate's `valid_to` field
- `error`: the underlying reason when invalid (expired, hostname mismatch, self-signed, connection timeout, etc.)

This runs on its own **1 hour** cycle, separate from the 5-minute RPC/API cycle - certificates don't change often enough to justify checking them every cycle, and a dedicated TLS handshake per endpoint per cycle would add unnecessary load. Because it's independent of the main protocol check, TLS data is still available for endpoints that are currently `Error` for unrelated reasons (e.g. the server is down at the application level but still terminates TLS correctly) - this is deliberate: knowing "is this actually a cert problem" is often most useful exactly when something else is already broken.

> ⚠️ TLS lookups must live outside the RPC/API request's own `try/catch`. An earlier version nested it inside, so any endpoint whose `/status` or `/cosmos/auth/...` request threw (i.e. any endpoint that was down) skipped the TLS lookup entirely and always reported `tls: null` - exactly the case where a TLS status is most useful. Fixed by moving the lookup before the network `try` block, so it always resolves regardless of whether the rest of the check succeeds.

---

## 🏆 Smart Endpoint Selector

A ranking API on top of the same aggregated data, for anyone who wants to consume "the best endpoint right now" programmatically instead of building their own selection logic client-side - wallets, dApps, indexers, or our own tooling as an automatic fallback if a primary endpoint goes down.

| Endpoint | Returns |
|---|---|
| `GET /rank-rpcs?chain={chain}` | Full list of RPC endpoints for `chain`, ranked |
| `GET /best-rpc?chain={chain}` | Just the current top RPC pick, as a single object |
| `GET /rank-apis?chain={chain}` | Full list of REST API endpoints for `chain`, ranked |
| `GET /best-api?chain={chain}` | Just the current top REST API pick |

Add `&region=US`, `&region=EU`, or `&region=CA` to rank by that region's smoothed latency instead of the global average - useful when you know where your users actually connect from.

**Ranking logic:**

1. Filter to endpoints that are currently healthy (`Synced` for RPC, `Working` for API) - an unhealthy endpoint is never returned regardless of its historical reliability
2. Sort by 7-day rolling `reliability` descending (the stable signal)
3. Break ties by smoothed latency (`smoothedAverageLatency`, or the region-specific smoothed sample when `region` is set) ascending - never the raw per-cycle latency, to avoid one noisy cycle reordering the list

```json
{
  "chain": "Celestia mainnet",
  "region": null,
  "generatedAt": "2026-09-01T09:05:16.664Z",
  "rpc": "https://celestia-rpc.example.com",
  "name": "ValidatorName"
}
```

`/rank-rpcs` and `/rank-apis` return the same shape but with an `endpoints` array (each entry including `rank`, `reliability`, `smoothedLatency`, `corsEnabled`, `tls`, `pruning`, `checkedAt`) instead of a single result. Served straight from the aggregator's existing 5-minute cache - no extra fetching, no extra load on the checkers.

---

## 📍 How Regional Latency Is Measured

The system currently includes agents in:

- 🇺🇸 United States (St. Louis)
- 🇪🇺 Europe (France)
- 🇨🇦 Canada

Each agent performs a **real application-layer query** from its region. Latency is measured from request sent to valid response received.

> ⚠️ If an endpoint returns an error (timeout, invalid JSON, HTTP 4xx/5xx), its latency is set to `null`. Error latencies are never included in averages or regional metrics.

---

## 🧠 Aggregator Logic

Both aggregators follow the same design:

1. Fetch results from all 3 regional agents simultaneously
2. Match endpoints by URL across regions
3. Aggregate latency samples per region into `latencyByRegion`, and smoothed samples into `smoothedLatencyByRegion`
4. Compute `averageLatency` and `smoothedAverageLatency` from valid samples only (null and >8s excluded)
5. Pass through `reliability` as reported by the agents
6. For `checkedAt` and `lastSuccessAt`, take the most recent timestamp across the 3 regional samples
7. For `pruning`, `corsEnabled`, `rateLimitHint`, and `tls`, take whichever regional sample is non-null closest to the most recent `checkedAt`, falling back to any region that has a non-null value - a single region temporarily missing one of these doesn't blank it out for the whole merged entry
8. Cache the merged result for 5 minutes
9. Expose at `/aggregate-rpcs` or `/aggregate-apis`
10. If a chain's validators file fails to parse on every region, the aggregator injects one synthetic entry (`name: "⚠️ Config Error"`) with the parser error in `detail`, so a broken config is visibly distinct from a chain with zero validators (RPC aggregator, since V4.1)
11. Also serves the [Smart Endpoint Selector](#-smart-endpoint-selector) routes (`/rank-rpcs`, `/best-rpc`, `/rank-apis`, `/best-api`), reading from the same cached, merged data - no separate fetch cycle

---

## 🔌 Public API Endpoints

| Checker Type | Public Endpoint |
|---|---|
| Tendermint / Cosmos RPC | [`https://aggregate-rpcs.cumulo.com.es/aggregate-rpcs`](https://aggregate-rpcs.cumulo.com.es/aggregate-rpcs) |
| EVM JSON-RPC | [`https://aggregate-evm-rpcs.cumulo.com.es/aggregate-evm`](https://aggregate-evm-rpcs.cumulo.com.es/aggregate-evm) |
| Cosmos REST API | [`https://aggregate-apis.cumulo.com.es/aggregate-apis`](https://aggregate-apis.cumulo.com.es/aggregate-apis) |
| RPC ranking (all endpoints) | `https://aggregate-rpcs.cumulo.com.es/rank-rpcs?chain={chain}` |
| RPC ranking (best only) | `https://aggregate-rpcs.cumulo.com.es/best-rpc?chain={chain}` |
| REST API ranking (all endpoints) | `https://aggregate-apis.cumulo.com.es/rank-apis?chain={chain}` |
| REST API ranking (best only) | `https://aggregate-apis.cumulo.com.es/best-api?chain={chain}` |

> `chain` must match a chain name exactly as it appears in `chains.json` (e.g. `Celestia mainnet`, `Cosmos mainnet`), URL-encoded. See [Smart Endpoint Selector](#-smart-endpoint-selector) for the ranking criteria and the optional `region` parameter.

---

## 📊 Example API Output

### RPC Node (`/aggregate-rpcs`)

```json
{
  "name": "Cumulo",
  "rpc": "https://mocha.celestia.rpc.cumulo.me",
  "status": "Synced",
  "block": "11574458",
  "indexing": "Indexed",
  "moniker": "Cumulo",
  "version": "0.38.17",
  "reliability": 100,
  "averageLatency": 406,
  "smoothedAverageLatency": 349,
  "latencyByRegion": [
    { "location": "CA", "ms": 506 },
    { "location": "US", "ms": 468 },
    { "location": "EU", "ms": 333 }
  ],
  "smoothedLatencyByRegion": [
    { "location": "CA", "ms": 427 },
    { "location": "US", "ms": 538 },
    { "location": "EU", "ms": 81 }
  ],
  "checkedAt": "2026-09-03T18:21:20.313Z",
  "lastSuccessAt": "2026-09-03T18:21:20.313Z",
  "pruning": "pruned",
  "corsEnabled": true,
  "rateLimitHint": null,
  "tls": {
    "valid": true,
    "daysUntilExpiry": 83,
    "error": null,
    "checkedAt": "2026-09-03T17:30:49.844Z"
  }
}
```

### REST API Node (`/aggregate-apis`)

```json
{
  "name": "Cumulo",
  "api": "https://api.cosmos.cumulo.com.es",
  "status": "Working",
  "detail": "bech32_prefix: cosmos",
  "reliability": 100,
  "averageLatency": 244,
  "smoothedAverageLatency": 258,
  "latencyByRegion": [
    { "location": "CA", "ms": 260 },
    { "location": "US", "ms": 111 },
    { "location": "EU", "ms": 362 }
  ],
  "smoothedLatencyByRegion": [
    { "location": "CA", "ms": 271 },
    { "location": "US", "ms": 130 },
    { "location": "EU", "ms": 340 }
  ],
  "checkedAt": "2026-09-03T18:21:20.317Z",
  "lastSuccessAt": "2026-09-03T18:21:20.317Z",
  "corsEnabled": true,
  "rateLimitHint": null,
  "tls": {
    "valid": true,
    "daysUntilExpiry": 71,
    "error": null,
    "checkedAt": "2026-09-03T17:30:49.957Z"
  }
}
```

### Rate limit hint, when a server exposes it (real example)

```json
"rateLimitHint": { "limit": null, "remaining": null, "retryAfter": 60 }
```

### Smart Endpoint Selector (`/best-rpc?chain=Celestia+mainnet`)

```json
{
  "chain": "Celestia mainnet",
  "region": null,
  "generatedAt": "2026-09-01T09:05:16.664Z",
  "rpc": "https://celestia-rpc.example.com",
  "name": "ValidatorName"
}
```

---

## ⏱️ Runtime Parameters

### RPC Checker (`server-rpc.js`)

| Parameter | Description | Value |
|---|---|---|
| `RPC_TIMEOUT_MS` | Max time per probe | **8,000 ms** |
| `CONCURRENCY_LIMIT` | Max simultaneous probes | **5** |
| `REFRESH_MS` | Full scan interval | **300,000 ms (5 min)** |
| `HISTORY_LIMIT` | Max reliability checks stored | **2,016 (~7 days)** |
| `CACHE_TTL` | Aggregator cache duration | **300,000 ms (5 min)** |
| `LATENCY_EMA_ALPHA` | Weight of the newest sample in the smoothed latency | **0.3** |
| `TLS_CHECK_INTERVAL` | TLS certificate re-check interval | **3,600,000 ms (1 hour)** |
| TLS handshake timeout | Per host, independent of the RPC timeout | **8,000 ms** |

### REST API Checker (`server-api.js`)

| Parameter | Description | Value |
|---|---|---|
| Timeout per probe | Via `node-fetch` timeout | **8,000 ms** |
| `CONCURRENCY_LIMIT` | Max simultaneous probes | **5** |
| `REFRESH_MS` | Full scan interval | **300,000 ms (5 min)** |
| `HISTORY_LIMIT` | Max reliability checks stored | **2,016 (~7 days)** |
| `CACHE_TTL` | Aggregator cache duration | **300,000 ms (5 min)** |
| `LATENCY_EMA_ALPHA` | Weight of the newest sample in the smoothed latency | **0.3** |
| `TLS_CHECK_INTERVAL` | TLS certificate re-check interval | **3,600,000 ms (1 hour)** |

### EVM JSON-RPC Checker (`server-evm.js`)

| Parameter | Description | Value |
|---|---|---|
| `RPC_TIMEOUT_MS` | Max time per probe | **5,000 ms** (configurable via env) |
| `CONCURRENCY_LIMIT` | Max simultaneous probes per chain | **8** (configurable via env) |
| `REFRESH_MS` | Full scan interval | **3,600,000 ms (1 hour)** (configurable via env) |
| `HISTORY_LIMIT` | Max reliability checks stored | **2,016 (~84 days at 1h intervals)** |
| Aggregator refresh | How often aggregator polls checkers | **30 seconds** |
| Snapshot persistence | Results saved to disk on every refresh | `data/check-evm.json` |

---

## 📏 How Reliability Is Calculated

Every 5 minutes each agent:

1. Executes the protocol-specific query
2. Determines if the endpoint is **up** (valid response) or **down** (error/timeout)
3. Stores a boolean in `reliability.json` or `reliability_apis.json`
4. Keeps only the **last 2,016 checks** (~7 days)
5. Calculates: `reliability% = (successful_checks / total_checks) × 100`

> For RPC: both `Synced` and `Not Synced` count as successful. Only `Error` counts as failure.
> For REST API: `Working` counts as successful. `Error` counts as failure.

---

## 📡 Validator List Management

Endpoint lists are managed through GitHub. Each validator entry can have both `rpc` and `api` fields:

```json
{
  "name": "ValidatorName",
  "rpc": "https://rpc.example.com",
  "api": "https://api.example.com"
}
```

- Validators without an `rpc` field are skipped by the RPC checker
- Validators without an `api` field are skipped by the API checker
- Changes to GitHub are reflected in dashboards within **5 minutes**

**Master chain index:** [`chains.json`](https://raw.githubusercontent.com/Cumulo-pro/Cumulo-Front-Chain/refs/heads/main/chains.json)

---

## 🛠️ Troubleshooting

### RPC endpoint works in browser but appears as Error

- check_d uses `GET /status` (not POST JSON-RPC) - should work on all standard proxies
- If still failing: check for datacenter IP blocks (403), rate limiting, or timeout > 8s
- Verify: `curl -o /dev/null -w "%{http_code}" https://rpc.example.com/status`

### REST API endpoint works in browser but appears as Error

- The checker queries `/cosmos/auth/v1beta1/bech32` - confirm this path is exposed
- Some API nodes only expose selected endpoints
- Verify: `curl https://api.example.com/cosmos/auth/v1beta1/bech32`

### A chain shows an empty list (0 endpoints) instead of expected results

This almost always means the chain's validators JSON file (e.g. `validators_testnet.json`)
has a syntax error - a single malformed entry breaks the parse for **the entire file**,
not just that one validator. All checkers hit the same GitHub file, so this affects every
region simultaneously.

- Symptom before V4.1: the chain silently returns `[]` - looks like "no validators configured", not "broken config".
- Symptom from V4.1 onward: a single `⚠️ Config Error` row appears with the parser's error message in its tooltip/detail.
- Fix: validate the file before committing - `python -m json.tool file.json` or `jq empty file.json` locally, or paste into any online JSON validator. A missing `:` or trailing comma is enough to take down the whole chain's dashboard.

### Reliability shows 0% for a new endpoint

Normal - history accumulates from first check. Stabilizes over 24–48 hours.

### The dashboard shows stale data

Agents scan every 5 min, aggregator caches 5 min → max 10 min stale. Refresh after waiting for next cycle.

### EU shows N/A for all API endpoints

Check that the EU API checker is running: `sudo systemctl status api-checker` on the EU server.
Note: the EU API checker may run on a non-standard port if 3005 is occupied by another service.

### EVM endpoint appears as Error but works in browser

The EVM checker uses `POST eth_blockNumber` - unlike RPC/API checkers, POST is correct here (EVM JSON-RPC is POST-only by spec). If it fails:
- Check for rate limiting or IP blocks
- Verify: `curl -X POST -H "Content-Type: application/json" -d '{"jsonrpc":"2.0","method":"eth_blockNumber","params":[],"id":1}' https://evm.example.com`

### EVM dashboard shows old data after restart

The EVM checker loads its last snapshot from `data/check-evm.json` on startup. New data arrives after the first full refresh cycle (up to 1 hour). Check `/health` endpoint for `updatedAt`.

### `tls: null` right after a checker restart

Normal for up to an hour. The first TLS pass is chained to run right after the first full RPC/API cycle completes, so it won't fire against an empty host list - but it still needs its own ~1-3 minute pass (concurrency-limited across every known host) before `tls` populates for anything. Subsequent restarts within the same hour keep serving whatever was last cached.

### An endpoint I know is archival shows `pruning: pruned`

`pruning` only reads the curated `archive_rpc` field from the chain's `validators.json` - it no longer infers anything from `earliest_block_height` (see [Pruning / Archival Classification](#-pruning--archival-classification)). If your archive endpoint isn't declared via `archive_rpc` in the validator list, it defaults to `pruned`. Add the field to your entry on GitHub to fix it; reflected within 5 minutes.

### `corsEnabled: false` but the endpoint works fine from a browser

The checker sends `Origin: https://cumulo.pro` and only marks `corsEnabled: true` if the response's `Access-Control-Allow-Origin` is `*` or that exact origin. A server that only reflects specific allowlisted origins (not `cumulo.pro`) will correctly show `false` here even though it works for whatever origins it does allow - this field answers "would cumulo.pro specifically be allowed," not "does this server support CORS in general."

---

## 📜 License

MIT © [Cumulo Pro](https://cumulo.pro)
