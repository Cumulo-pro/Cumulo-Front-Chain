# 🛰️ check_d — Decentralized Endpoint Monitoring Tool

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

- Executes **real RPC calls** via `GET /status` (Tendermint/Cosmos) or `eth_blockNumber` (EVM)
- Measures **latency from multiple regions** (🇺🇸 US, 🇪🇺 EU, 🇨🇦 CA)
- Collects metrics such as **average latency, block height, reliability**, and sync status
- Exposes a structured **JSON API** for programmatic access
- Provides per-network **dashboards** for live comparison and exploration

---

## 📊 What Does It Measure?

Each endpoint is tested using a **real application-level request** depending on its protocol:

| Protocol | Query Used |
|---|---|
| Tendermint / Cosmos SDK RPC | `GET /status` |
| EVM-compatible JSON-RPC | `eth_blockNumber` |
| gRPC | `grpc.health.v1.Health/Check` |
| REST API | `GET /node_info` or equivalent |

> ⚠️ **Important:** check_d uses `GET /status` (HTTP REST) rather than `POST` JSON-RPC for Tendermint endpoints. This avoids false negatives on nodes whose reverse proxy (nginx/Cloudflare) only exposes the REST interface, which is the standard public configuration.

These queries:

- Are sent from **distributed regional agents**
- Measure actual **response time (latency)**
- Confirm whether the node **responds correctly with valid data**
- Extract additional metadata: block height, version, moniker, sync flags, etc.

---

## 🌐 Decentralized Architecture

The system consists of three independent layers:

```
[ US Agent - server-rpc.js ]  ─┐
[ EU Agent - server-rpc.js ]  ─┼──▶  Aggregator  ──▶  /aggregate-rpcs  ──▶  Dashboards / APIs
[ CA Agent - server-rpc.js ]  ─┘
```

### Current Infrastructure

| Role | Region |
|---|---|
| Checker | 🇺🇸 St. Louis, US |
| Checker | 🇪🇺 France, EU |
| Checker | 🇨🇦 Canada, CA |
| Aggregator | 🇺🇸 St. Louis, US |

The decentralized nature of the agents makes it easy to expand across more data centers and jurisdictions, increasing transparency and trust.

---

## ✅ Design Guarantees

| Feature | Description |
|---|---|
| Real RPC calls | Uses `GET /status` or `eth_blockNumber` — not synthetic ping or TCP handshakes |
| Multi-region probes | Simulates real-world user experience from US, EU, and CA |
| Uniform logic | All nodes in a network are tested using the exact same rules |
| Transparent data | All endpoints, scripts, and configurations are open and auditable |
| Extensible design | Easily supports new chains and protocols via `chains.json` config |
| Public results | JSON API and web dashboards are open to the public, no paywalls |
| Anti-overlap protection | Each agent skips a new cycle if the previous one is still running |
| Aggregator cache | The aggregator caches results for 5 minutes, responding instantly to all requests |

---

## 🔧 Improvements Over Traditional Checkers

| Aspect | Legacy Tools | **check_d** |
|---|---|---|
| Real latency | ❌ No (uses ping/TCP) | ✅ Yes, real protocol calls |
| Decentralized agents | ❌ No | ✅ Yes, globally distributed |
| Cross-chain support | ❌ Limited | ✅ Yes (EVM, Cosmos, gRPC, etc.) |
| Extensible | ❌ Closed | ✅ Open-source and modular |
| Validator comparison | ❌ Not supported | ✅ Yes, per-provider latency and reliability |
| Detailed metrics | ❌ Minimal | ✅ Full latency, sync, block height, reliability |
| False negatives | ❌ Common (POST JSON-RPC issues) | ✅ Avoided via `GET /status` |

---

## 📈 Who Is It For?

- Validator operators comparing the quality of their RPCs against others
- Protocol teams monitoring public infrastructure
- Delegators choosing high-quality validators
- Indexers and explorers selecting fast and reliable endpoints
- dApp developers seeking the best RPC provider per region

---

## 🔁 How It Works (Step by Step)

1. A hosted [`chains.json`](https://raw.githubusercontent.com/Cumulo-pro/Cumulo-Front-Chain/refs/heads/main/chains.json) file defines which chains to test and where to fetch RPC endpoint lists
2. Each regional agent (`server-rpc.js`) fetches the validator list for each chain from GitHub every 5 minutes
3. Each endpoint is queried concurrently (up to 5 simultaneous probes) using `GET /status`
4. Results — including latency, block height, moniker, version, and sync status — are stored in memory
5. A reliability history is persisted to `reliability.json` (up to 2,016 entries per endpoint, ~7 days at 5-min intervals)
6. The central aggregator merges results from all regional agents, computes average latency, and caches the combined output for 5 minutes
7. The public API and dashboards consume the aggregator endpoint

---

## 📍 How Regional Latency Is Measured

The system currently includes agents in:

- 🇺🇸 United States (St. Louis)
- 🇪🇺 Europe (France)
- 🇨🇦 Canada

Each agent performs a **real `GET /status` call** from its region:

- Not just pings — actual application-layer queries
- Latency is measured from the moment the request is sent to when a valid JSON response is received
- Full node metadata is retrieved: block height, sync status, version, moniker, tx_index

> ⚠️ If an endpoint returns an error (timeout, invalid JSON, HTTP 4xx/5xx), its latency is set to `null`. Error latencies are never included in averages or regional metrics.

---

## 🧠 Aggregator Logic

The central aggregator (`aggregator.js`) performs:

1. Fetches results from all regional agents simultaneously
2. Matches endpoints across regions by RPC URL
3. Aggregates latency samples per region into `latencyByRegion`
4. Computes `averageLatency` from all valid samples (excluding nulls and values > 8s)
5. Passes through `reliability` as reported by the primary agent
6. Caches the complete merged result for 5 minutes
7. Exposes the merged result at `/aggregate-rpcs`

### Aggregator Design Principles

The aggregator is intentionally **transparent**:
- It does **not** normalize or reinterpret results
- It does **not** hide or smooth error states
- It only matches, merges, and exposes — all logic lives in the agents

---

## 🔌 Public API Endpoints

check_d exposes live monitoring data through structured JSON APIs:

| Checker Type | Public Endpoint |
|---|---|
| Tendermint / Cosmos RPC | [`https://aggregate-rpcs.cumulo.com.es/aggregate-rpcs`](https://aggregate-rpcs.cumulo.com.es/aggregate-rpcs) |
| EVM JSON-RPC | [`https://aggregate-evm-rpcs.cumulo.com.es/aggregate-evm`](https://aggregate-evm-rpcs.cumulo.com.es/aggregate-evm) |
| REST API | [`https://aggregate-apis.cumulo.com.es/aggregate-apis`](https://aggregate-apis.cumulo.com.es/aggregate-apis) |

Each API provides:
- Per-region latency (🇺🇸 US, 🇪🇺 EU, 🇨🇦 CA)
- Average latency across all regions
- Block height
- Reliability percentage (last ~7 days)
- Endpoint status (`Synced`, `Not Synced`, `Error`)
- Node metadata: version, moniker, tx_index, node_id

---

## 📊 Example API Output (Per RPC Node)

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

---

## ⏱️ Runtime Parameters

Each regional agent runs with the following configuration:

| Parameter | Description | Value |
|---|---|---|
| `RPC_TIMEOUT_MS` | Max time per endpoint probe before marking as Error | **8,000 ms** |
| `CONCURRENCY_LIMIT` | Max simultaneous probes per scan cycle | **5** |
| `REFRESH_MS` | Full scan interval | **300,000 ms (5 min)** |
| `HISTORY_LIMIT` | Max reliability checks stored per endpoint | **2,016 (~7 days)** |
| `CACHE_TTL` | Aggregator cache duration | **300,000 ms (5 min)** |

> ⚠️ Endpoints that do not respond within 8 seconds are marked as `Error`. This is intentional — a node that slow is not useful for real-world usage.

---

## 📏 How Reliability Is Calculated

Every time a regional agent runs a check (every **5 minutes**), it:

1. Executes a `GET /status` request to the endpoint
2. Determines whether the endpoint is **up**:
   - ✅ *Up* if the response contains a valid JSON with `sync_info` and `node_info`
   - ❌ *Down* if there is no response, a timeout, an HTTP error, or invalid JSON
3. Stores a boolean (`true` = up, `false` = down) in `reliability.json` for that endpoint URL
4. Keeps only the **last 2,016 checks** per endpoint (~7 days at 5-min intervals)
5. Calculates reliability as:

```
reliability% = (successful_checks / total_checks) × 100
```

> **Note:** Both `Synced` and `Not Synced` count as successful. Only `Error` counts as a failure.

### Interpreting Reliability Correctly

Reliability reflects **historical availability**, not current health.

| Scenario | What You See |
|---|---|
| Node failed once in 1,000 checks | 99.9% (rounds to 100%) |
| Node just went down | Still shows previous high % |
| Node restarted today | History accumulates from first check |

Always correlate reliability with current **status**, **latency**, and **block height freshness**.

---

## 📡 Validator List Management

The list of endpoints to monitor is managed through GitHub:

- **Master chain index:** [`chains.json`](https://raw.githubusercontent.com/Cumulo-pro/Cumulo-Front-Chain/refs/heads/main/chains.json)
- **Per-chain validator lists:** e.g., [`Celestia/data/validators_testnet.json`](https://raw.githubusercontent.com/Cumulo-pro/Cumulo-Front-Chain/refs/heads/main/Celestia/data/validators_testnet.json)

To **add or remove a validator**, edit the corresponding JSON file on GitHub. The change will be reflected in the dashboard within **5 minutes** (the next agent scan cycle).

Each validator entry format:
```json
{ "name": "ValidatorName", "rpc": "https://rpc.example.com" }
```

---

## 🛠️ Troubleshooting

### An endpoint works in the browser but appears as Error

**Most common cause:** The node's reverse proxy (nginx/Cloudflare) only exposes `GET /status` via HTTP REST, not `POST /` JSON-RPC. check_d V4+ uses `GET /status` exclusively, which resolves this.

Other possible causes:
- Response exceeds `RPC_TIMEOUT_MS` (8 seconds)
- Endpoint blocks requests from datacenter IP ranges (403 Forbidden)
- Valid TLS/TCP but invalid JSON response body
- Rate limiting or throttling

Always verify:
- The HTTP response code: `curl -o /dev/null -w "%{http_code}" https://rpc.example.com/status`
- The response body: `curl https://rpc.example.com/status | head -5`

### Reliability shows 0% for a new endpoint

Normal behaviour. The history starts empty and accumulates over time. After the first successful check, reliability will show 100%. It stabilizes over 24–48 hours.

### The dashboard shows stale data

The aggregator caches results for 5 minutes. The regional agents scan every 5 minutes. In the worst case, data can be up to **10 minutes old**. Refresh the page after waiting for the next cache cycle.

---

## 📜 License

MIT © [Cumulo Pro](https://cumulo.pro)
