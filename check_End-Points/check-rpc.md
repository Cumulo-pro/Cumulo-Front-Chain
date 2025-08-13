# 🛰️ check_d — Decentralized Endpoint Monitoring Tool

**check_d** is a decentralized monitoring system designed to evaluate the health, availability, synchronization, and performance of public blockchain endpoints. Unlike traditional tools, it performs **real protocol-level queries** (JSON-RPC, gRPC, REST) from **multiple geographic locations**, generating reproducible metrics for infrastructure observability.

## 🚀 Why This Tool?

In a growing modular blockchain ecosystem, access to reliable public nodes is critical for:

- Block explorers
- Indexers and index services
- Cross-chain bridges
- Decentralized applications (dApps)
- Staking infrastructure and delegators

Yet most existing endpoint checkers are **centralized**, based on pings or TCP checks, and **fail to reflect real-world performance**. They also lack transparency, regional insights, and fair comparison models.

**check_d** was built to address these limitations by offering a decentralized, extensible, and transparent monitoring system that:

- Executes **real RPC or gRPC calls** (e.g., `eth_blockNumber`, `/status`, etc.)
- Measures **latency from multiple regions** (🇺🇸 US, 🇪🇺 EU, 🇨🇦 CA, ...)
- Collects metrics such as **average latency, block height, uptime**, and sync status
- Exposes a structured **JSON API** for programmatic access (`/aggregate-evm`)
- Provides per-network **dashboards** for live comparison and exploration

## 📊 What Does It Measure?

Each endpoint is tested using a **real application-level request** depending on its protocol:

- `eth_blockNumber` for EVM-compatible chains
- `GET /status` for Tendermint / Cosmos SDK chains
- `grpc.health.v1.Health/Check` for gRPC endpoints
- `GET /node_info` or similar for REST APIs

These queries:

- Are sent from **distributed regional agents**
- Measure actual **response time (latency)**
- Confirm whether the node **responds correctly with valid data**
- Extract additional metadata: block height, version, moniker, sync flags, etc.

## 🌐 Decentralized Architecture

The system consists of:

- **Regional agents** (`server-evm.js`, etc.) that run independently in US, EU, CA, etc.
- A central **aggregator** (`aggregator-evm.js`) that merges all results without modifying them
- A public API (`/aggregate-evm`) that returns structured JSON grouped by chain

The decentralized nature of the agents makes it easy to expand across more data centers and jurisdictions, increasing transparency and trust.

## ✅ Why Is It Trustworthy?

| Feature              | Description                                                                 |
|----------------------|-----------------------------------------------------------------------------|
| Real RPC calls       | Uses application-level queries (not synthetic ping or TCP handshakes)       |
| Multi-region probes  | Simulates real-world user experience from different locations               |
| Uniform logic        | All nodes in a network are tested using the exact same rules                |
| Transparent data     | All endpoints, scripts, and configurations are open and auditable           |
| Extensible design    | Easily supports new chains and protocols via config or plugins              |
| Public results       | JSON API and web dashboards are open to the public, no paywalls             |

## 🔧 Improvements Over Traditional Checkers

| Aspect               | Legacy Tools         | **check_d**                                |
|----------------------|----------------------|---------------------------------------------|
| Real latency         | ❌ No (uses ping/TCP) | ✅ Yes, real protocol calls                 |
| Decentralized agents | ❌ No                | ✅ Yes, globally distributed                |
| Cross-chain support  | ❌ Limited           | ✅ Yes (EVM, Cosmos, gRPC, etc.)           |
| Extensible           | ❌ Closed            | ✅ Open-source and modular                 |
| Validator comparison | ❌ Not supported     | ✅ Yes, per-provider latency/Uptime        |
| Detailed metrics     | ❌ Minimal           | ✅ Full latency, sync, block height, etc.  |

## 📈 Who Is It For?

- Validator operators comparing the quality of their RPCs against others
- Protocol teams monitoring public infrastructure
- Delegators choosing high-quality validators
- Indexers and explorers selecting fast and reliable endpoints
- dApp developers seeking the best RPC provider per region

## 🔁 How It Works (At a High Level)

- A hosted `chains.json` file defines which chains to test and where to fetch RPC endpoint lists
- Each endpoint is queried concurrently with strict timeout and validation
- Aggregators group per-chain results, calculating:
  - Node-by-node status
  - Regional and global latency
  - Sync and availability metrics
- Results are served as JSON (`/aggregate-evm`) and rendered in a web dashboard
- System runs every 5 minutes, skipping nodes with invalid responses

## 📍 How Regional Latency Is Measured

The system currently includes agents in:

- 🇺🇸 United States
- 🇪🇺 Central Europe
- 🇨🇦 Canada

Each agent performs a **real RPC call** from its region:

- Not just pings — actual protocol-level queries (JSON-RPC, gRPC, REST)
- Latency is measured at the application layer
- Full node metadata is retrieved (block height, sync status, version)

## 🧠 Aggregator Logic

A central service (`aggregator-evm.js`) performs:

1. Endpoint matching across agents
2. Aggregation of latency data per region
3. Calculation of global averages
4. Output to `/aggregate-evm` for frontend and API usage

This enables **multi-perspective analysis** of each endpoint.

## 🔌 Public Aggregator Endpoints

check_d currently exposes live monitoring data through two structured JSON APIs:

- **EVM-compatible RPCs**:  
  [`/aggregate-evm`](https://aggregate-evm-rpcs.cumulo.com.es/aggregate-evm)  
  → Returns metrics for Ethereum-like endpoints.

- **Cosmos / Tendermint RPCs**:  
  [`/aggregate-rpcs`](https://aggregate-rpcs.cumulo.com.es/aggregate-rpcs)  
  → Returns metrics for Tendermint-style endpoints using.

Each API provides:
- Per-region latency (🇺🇸, 🇪🇺, 🇨🇦)
- Average latency
- Block height per region
- Uptime percentage over the past 7 days
- Endpoint status (OK, Error + reason)
- Node metadata (version, moniker, etc.)

## 📊 Example Output (Per RPC Node)

```json
{
  "name": "Cumulo Pro",
  "rpc": "https://rpc.xrpl.evm.cumulo.com.es",
  "status_eu": "OK",
  "latency_eu": 176,
  "status_us": "OK",
  "latency_us": 298,
  "status_ca": "OK",
  "latency_ca": 267,
  "block_eu": "0x1a3cde",
  "uptime_eu": 99.65
}
```

---

## 📏 How Uptime Is Calculated

Every time a regional agent runs a check (by default, every **5 minutes**), it:

1. Executes a **real protocol-level query** (e.g., `/status` on Tendermint RPC).
2. Determines whether the endpoint is considered **up**:
   - ✅ *Up* if the query returns a valid JSON structure and no fatal error occurs.  
     *(Both `Synced` and `Not Synced` states count as up — only `"Error"` is considered down.)*
   - ❌ *Down* if there is no response, a timeout, or an invalid JSON structure.
3. Stores a boolean (`true` = up, `false` = down) in a persistent history (`uptime.json`) for that exact endpoint URL.
4. Keeps only the **last 2,048** checks per endpoint (oldest entries are discarded).
5. Calculates uptime percentage as:  

   ```
   uptime% = (number_of_true / total_checks) × 100
   ```

### Important Notes
- If a node is stopped **between two check cycles**, it will still show its last recorded status until the next scheduled run.
- With a long history, a single failure may not significantly change the percentage — e.g.,  
  1 failed check out of 1,000 = 99.9% (rounded to 100% if using integer rounding).
- The uptime value is **region-independent** — it reflects whether the endpoint responded at all from that agent’s perspective, not whether it was fast or slow.
- URL normalization matters: the same RPC endpoint with and without a trailing slash is tracked separately unless normalized.

---

📜 License  
MIT © [Cumulo Pro](https://cumulo.pro)
