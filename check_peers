# 🛰️ Decentralized Peer Monitor 

A professional-grade tool designed by Cumulo Pro to monitor and evaluate the reliability of P2P peers in Cosmos SDK-based networks (initially Celestia). It aggregates and analyzes data from network peers using a distributed and auditable process.

---

## 📌 Overview

This tool performs scheduled scans of peer-to-peer (P2P) networks using publicly available RPC endpoints of blockchain validators. It stores and aggregates information about active peers, measures their response latency, enriches IP data with geolocation metadata, and generates a historical dataset to evaluate long-term availability (uptime) and performance (latency score).

---

## 🧩 Project Structure

```
project-root/
├── server-peers.js           # Fetches and stores current peers from public RPC endpoints
├── analyze_peers.js          # Processes historical peer snapshots and calculates uptime, latency, score
├── history/                  # Folder containing daily peer snapshots
├── peers.json                # Latest snapshot of discovered peers
├── analyze_output.json       # Final structured output grouped by country/region
├── systemd.err.log           # Log file of any RPC failures or unexpected responses
```

---

## 🔁 Peer Scanner (`server-peers.js`)

This script:

1. Loads a list of Cosmos SDK-based chains from `chains_peers.json`, each with a link to validator metadata.
2. For each chain:
   - Extracts all `rpc` endpoints from validator metadata.
   - Sends a GET request to `/net_info` on each endpoint.
   - Parses peer information (`node_id`, `moniker`, `ip`, `port`, `version`).
3. Validates if the peer's IP is public.
4. Performs a **geo-IP enrichment** via `ip-api.com` for valid IPs.
5. Stores:
   - A current snapshot in `peers.json`
   - A timestamped file in `history/` (e.g. `peers_2025-08-03T16-20.json`)
6. Deletes historical files older than 30 days.

### Example command:
```bash
node server-peers.js
```

### Cron job idea:
```cron
0 */4 * * * /usr/bin/node /path/to/server-peers.js
```

---

## 📊 Peer Analyzer (`analyze_peers.js`)

This script:

1. Scans all `peers_*.json` files in the `history/` directory.
2. Aggregates unique peers (by `node_id@ip:port`).
3. **Measures real-time latency** using ICMP ping (`ping -c 1 -W 1 <ip>`).
4. **Calculates peer uptime**:
   - Based on the number of snapshots in which a given peer appeared.
   - Expressed as a percentage relative to the total number of scans.
5. **Assigns a score** based on latency:
   - `< 50ms`: 100
   - `< 100ms`: 80
   - `< 200ms`: 50
   - Else: 20
6. Outputs final report in `analyze_output.json`, grouped by:
   - `continent`
   - `country`

---

## 📈 Metrics in `analyze_output.json`

Each peer entry contains:

- `moniker`: validator name
- `ip`, `port`: networking data
- `node_id`: peer identifier
- `version`: software version
- `uptime`: appearance rate across historical snapshots (0–100%)
- `latency_ms`: real-time ping latency
- `score`: performance score based on latency
- `chain`: chain name from `chains_peers.json`

---

## 📐 Scoring & Evaluation Strategy

| Metric        | Source                 | Purpose                                |
|---------------|------------------------|----------------------------------------|
| `uptime`      | History of appearance  | Availability indicator                 |
| `latency_ms`  | Real-time ping         | Responsiveness measure                 |
| `score`       | Derived from latency   | Used for ranking and prioritization    |
| `geo`         | IP enrichment          | Geographic diversity, relay targeting  |

---

## 🌍 Geolocation Strategy

We use the free API at `http://ip-api.com/json/{ip}` to classify nodes by:

- Continent
- Country
- ISP
- City

This allows visualizations, distribution analysis, and policy-aware relay selection.

---

## 🔐 Reliability & Fault Handling

- Uses per-request timeouts (15 seconds) via `AbortController`.
- Logs failures to `systemd.err.log`.
- Automatically skips malformed validator files or non-responsive RPC endpoints.
- Spaced API calls (`sleep 1500ms`) to avoid rate-limiting by IP APIs.

---

## 🛠️ Future Improvements

- Multi-region latency testing (via distributed probe agents)
- Peer scoring algorithm with uptime weighting
- CLI summary tool for peer analysis
- Peer export to Prometheus/Grafana format
- Web-based frontend (GeoMap + Metrics)

---

## ✅ Designed for Infra Teams

This tool is built for validators and infrastructure teams seeking to:

- Evaluate the stability of their peers
- Select low-latency, high-availability peers
- Maintain a healthy gossip layer in Cosmos SDK chains (e.g., Celestia)

---

Maintained with 🛰️ by **Cumulo Pro** — [https://cumulo.pro](https://cumulo.pro)
