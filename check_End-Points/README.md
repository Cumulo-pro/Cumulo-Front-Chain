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
2. For each validator with an `rpc` field, sends `GET {rpc}/status`
3. Parses `sync_info` and `node_info` from the response
4. Records latency, block height, moniker, version, tx_index
5. Saves a reliability history entry to `reliability.json`
6. Serves results at `:3003/check-rpcs`

### REST API Checker (`server-api.js`)

1. Reads the same [`chains.json`](https://raw.githubusercontent.com/Cumulo-pro/Cumulo-Front-Chain/refs/heads/main/chains.json)
2. For each validator with an `api` field, sends `GET {api}/cosmos/auth/v1beta1/bech32`
3. Checks the response for a valid `bech32_prefix` field
4. Records latency and working/error status
5. Saves a reliability history entry to `reliability_apis.json`
6. Serves results at `:3005/check-apis` (or `:3006` if port is occupied)

Both RPC and API checkers run every **5 minutes** with anti-overlap protection.

### EVM JSON-RPC Checker (`server-evm.js`)

1. Reads the same [`chains.json`](https://raw.githubusercontent.com/Cumulo-pro/Cumulo-Front-Chain/refs/heads/main/chains.json)
2. For each validator with an `evm` field, sends `POST {evm}` with body `{"method":"eth_blockNumber",...}`
3. Parses the hex block number from the response (`data.result`)
4. Records latency (null on error) and reliability history to `evm-reliability.json`
5. **Persists results to disk** (`data/check-evm.json`) - survives restarts with warm cache
6. Serves results at `:3004/check-evm`

> ⚠️ The EVM checker refreshes every **1 hour** (not 5 minutes) - EVM block times are much faster and the checker is more resource-intensive due to Keep-Alive connection pooling across many chains.

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
3. Aggregate latency samples per region into `latencyByRegion`
4. Compute `averageLatency` from valid samples only (null and >8s excluded)
5. Pass through `reliability` as reported by the agents
6. Cache the merged result for 5 minutes
7. Expose at `/aggregate-rpcs` or `/aggregate-apis`

---

## 🔌 Public API Endpoints

| Checker Type | Public Endpoint |
|---|---|
| Tendermint / Cosmos RPC | [`https://aggregate-rpcs.cumulo.com.es/aggregate-rpcs`](https://aggregate-rpcs.cumulo.com.es/aggregate-rpcs) |
| EVM JSON-RPC | [`https://aggregate-evm-rpcs.cumulo.com.es/aggregate-evm`](https://aggregate-evm-rpcs.cumulo.com.es/aggregate-evm) |
| Cosmos REST API | [`https://aggregate-apis.cumulo.com.es/aggregate-apis`](https://aggregate-apis.cumulo.com.es/aggregate-apis) |

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
  "latencyByRegion": [
    { "location": "CA", "ms": 506 },
    { "location": "US", "ms": 468 },
    { "location": "EU", "ms": 333 }
  ]
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
  "latencyByRegion": [
    { "location": "CA", "ms": 260 },
    { "location": "US", "ms": 111 },
    { "location": "EU", "ms": 362 }
  ]
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

### REST API Checker (`server-api.js`)

| Parameter | Description | Value |
|---|---|---|
| Timeout per probe | Via `node-fetch` timeout | **8,000 ms** |
| `CONCURRENCY_LIMIT` | Max simultaneous probes | **5** |
| `REFRESH_MS` | Full scan interval | **300,000 ms (5 min)** |
| `HISTORY_LIMIT` | Max reliability checks stored | **2,016 (~7 days)** |
| `CACHE_TTL` | Aggregator cache duration | **300,000 ms (5 min)** |

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

---

## 📜 License

MIT © [Cumulo Pro](https://cumulo.pro)
