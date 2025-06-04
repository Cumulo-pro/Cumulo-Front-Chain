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

---

With **real queries**, **distributed agents**, and **open aggregation**, `check_d` offers a transparent and reliable monitoring solution for blockchain infrastructure — trusted by validators, developers, and communities.

---

🔁 How It Works (At a High Level)
- A hosted `chains.json` file defines which chains to test and where to fetch lists of RPC endpoints.
- Each endpoint is queried concurrently using JSON-RPC with strict timeout control.
- A per-chain aggregation summarizes:
  - Node-by-node statuses
  - Per-region latency measurements and average
  - Availability and sync status
- The results are served as JSON (`/check-rpcs`) and rendered in a responsive frontend dashboard.
- The frontend includes sortable columns for live analysis and comparison.
- The system is designed to run continuously and autonomously, refreshing the data every 5 minutes and ignoring nodes that return invalid or malformed responses.

🔐 Why It's Trustworthy
✅ Uses real RPC queries — no synthetic checks or hardcoded assumptions.  
🧪 Applies the same logic across all endpoints for consistency.  
📉 Excludes nodes with unreachable or invalid RPCs from metrics (no skew).  
📂 All chains and endpoints are publicly listed in GitHub (auditable).  
🚫 No proprietary APIs — only open, documented standards like Tendermint `/status`.  
🖥 Server locations are fixed and transparent (latency is geographically relative, but consistent).  
💡 Data is kept read-only and is not modified or filtered beyond validation.  

👥 Intended Audience
- Validator operators looking to audit RPC uptime across competitors.
- Blockchain core teams monitoring network health.
- Delegators evaluating infrastructure quality.
- Bridge operators or indexers verifying endpoint reliability.

## 🌐 Distributed Latency Measurement

Unlike traditional uptime checkers that test from a single server, **check_rpc** uses a distributed aggregation system to provide **geographically-aware performance insights**. This makes latency measurements **realistic, transparent, and representative** of what users experience globally.

---

### 📍 How Regional Latency Is Measured

The system is composed of multiple regional agents, each executing the same `check_rpc` script from different data centers:

- 🇺🇸 **United States**
- 🇪🇺 **Central Europe**
- 🇬🇧 **United Kingdom**

Each agent performs a real JSON-RPC call (typically `GET /status`) against each endpoint listed. Unlike synthetic ping tests, these are **live application-level RPC queries**, returning:

- Full block height and node status
- HTTP response latency
- Node metadata (ID, moniker, version)
- Indexing and synchronization flags

---

### 🧠 Aggregator Logic

A central service (`aggregator.js`) collects the JSON outputs from all active regional agents. It merges and processes the results using the following logic:

1. **Match endpoints** across all sources by `rpc` URL.
2. **Store each regional latency sample** under `latencySamples`.
3. **Compute `averageLatency`** from all valid samples.
4. **Expose** both regional and average metrics through `/aggregate-rpcs`.

This allows every RPC node to be evaluated **per location** and across **regions**, offering detailed observability.

### 💡 Why It Matters

- 🔬 **Latency is relative** — this model captures true RPC responsiveness per region.
- 🛠 **Validators and node providers** can compare how their infrastructure performs globally.
- 🌎 **Delegators, bridges, and explorers** can choose RPCs with optimal proximity.
- 📉 **Detect asymmetric networking issues** or overloaded regional nodes.

---

### 🔐 Trust & Reproducibility

| Property                  | Description                                                                 |
|---------------------------|-----------------------------------------------------------------------------|
| ✅ Real Queries           | Uses standard JSON-RPC `/status` calls — no fakes or mocks                  |
| 🧪 Uniform Evaluation     | All endpoints are tested under identical logic                              |
| 🌍 Regional Transparency  | Server regions are fixed and documented                                     |
| 🚫 Invalid Nodes Skipped  | Errors and non-responders are excluded from latency averages                |
| 📁 Open Definitions       | Chain & endpoint definitions are public (e.g. `chains.json` on GitHub)      |

---

With **regional probing** and **smart aggregation**, `check_rpc` becomes a powerful and transparent monitoring layer for blockchain RPC infrastructure — **trusted by validators, explorers, and protocol teams alike**.

---

### 📊 Example Output (Per RPC Node)

```json
{
  "name": "Polkachu",
  "rpc": "https://xrp-testnet-rpc.polkachu.com",
  "status": "Synced",
  "block": "1487768",
  "indexing": "Indexed",
  "moniker": "hello-xrp-testrelay",
  "node_id": "737b3b337173cc00830a43314cff8d6a1ae8b046",
  "version": "0.38.17",
  "isValidator": false,
  "latencySamples": [
    { "location": "US", "ms": 347 },
    { "location": "EU", "ms": 205 }
  ],
  "averageLatency": 276,
  "latencyByRegion": [
    { "location": "US", "ms": 347 },
    { "location": "EU", "ms": 205 }
  ]
}
```

📜 License
MIT © [Cumulo Pro](https://cumulo.pro)
