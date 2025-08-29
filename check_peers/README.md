# 🛰️ DEcentralized Peer Monitor

A professional‑grade toolkit designed by **Cumulo Pro** to monitor and evaluate the reliability of P2P peers in blockchains networks. It aggregates and analyzes data from network peers using a distributed and auditable process, then exposes ranked views for operators and UIs (PeerScan & Connectivity).

---

## 📌 Overview

This toolkit performs scheduled scans of peer‑to‑peer (P2P) networks using validator **RPC endpoints**. It stores and aggregates information about active peers, measures response latency (globally and per region), enriches IPs with geolocation, and builds a historical dataset to evaluate long‑term availability (**uptime**) and performance (**scores**). A small **API proxy** serves a normalized JSON that powers the frontend (PeerScan & Connectivity pages).

---

## 🧩 Project Structure

```
project-root/
├── chains_peers.json          # Chain catalog → points to validator metadata that include RPC endpoints
├── server-peers.js            # (1) Discovery: fetch peers from /net_info, enrich geo-IP, write snapshots
├── analyze_peers.js           # (2) Analysis: compute uptime, latency (global/region), scores, top-by-region
├── peer_analyze.php           # (3) API proxy: serves the final JSON to the frontend
├── history/                   # Timestamped peer snapshots (rolling window ~30 days)
│   └── peers_2025-08-03T16-20.json
├── peers.json                 # Latest live snapshot (flat)
├── data/
│   └── analyze-dashboard      # Final structured JSON consumed by the web UI
├── logs/
│   └── systemd.err.log        # Failures, timeouts, malformed payloads, etc.
└── README.md
```

---

## 🛠 Scripts (What runs where)

### 1) `server-peers.js` — Discovery & Snapshots

**What it does**
- Reads **`chains_peers.json`** (each chain points to a validator metadata JSON).
- Extracts **`rpc`** endpoints and calls `GET /net_info` to discover peers (`node_id@ip:port`, `moniker`, `version`).
- Filters **public IPs**, enriches with geo‑IP (`ip-api.com`), and writes snapshots.

**Inputs**
- `chains_peers.json` (validator metadata sources).

**Outputs**
- `peers.json` — latest live snapshot (flat list).
- `history/peers_YYYY-MM-DDTHH-mm.json` — historical snapshots; retained ~30 days.
- `logs/systemd.err.log` — RPC failures, timeouts, malformed payloads.

**Config (env)**
- `RPC_TIMEOUT_MS` (default: `15000`)
- `RPC_CONCURRENCY` (e.g., `8–16`)
- `IP_API_BASE` (default: `http://ip-api.com/json/`)
- `IP_API_SLEEP_MS` (default: `1500`)
- `HISTORY_RETENTION_DAYS` (default: `30`)
- `CHAIN_FILTER` (optional: process only that chain)

**Run & Schedule**
```bash
node server-peers.js
# cron every 4h
0 */4 * * * /usr/bin/node /path/to/server-peers.js
```

---

### 2) `analyze_peers.js` — Aggregation, Scoring & “analyze-dashboard”

**What it does**
- Loads `history/peers_*.json`, **deduplicates** peers by `node_id@ip:port`.
- Computes **uptime%** across the ~30‑day rolling window: `(appearances / snapshots) × 100`.
- **Normalizes region labels** (`Americas/*` → `America/*`) for consistency.
- Computes **latency** (global average) and **per‑region** averages (ignores `< 1 ms` as LAN noise).
- Assigns **scores** (global and per region) and builds:
  - `chains[chain].all` → full peer dataset with metrics.
  - `chains[chain].top_by_region[Region]` → ranked lists per region.
- Produces **Top‑N by region** (default **12**) ordered by:
  1) `score_region` (desc),
  2) `latency_ms` (asc),
  3) `uptime` (desc),
  4) `moniker` (asc; tie‑breaker).

**Outputs**
- `data/analyze-dashboard` (JSON for the web UI).

**Config (env)**
- `TOP_N_PER_REGION` (default: `12`)
- `REGION_NORMALIZE_AMERICAS` (default: `true`)
- `OUTPUT_PATH` (default: `data/analyze-dashboard`)

**Run & Schedule**
```bash
node analyze_peers.js
# cron 5 min after discovery
5 */4 * * * /usr/bin/node /path/to/analyze_peers.js
```

**Notes**
- Global score combines latency + uptime on a 0–100 scale.
- Region score is derived from latency observed in that region (and its consistency / sample size).
- Latencies `< 1 ms` are discarded (considered noise).

---

### 3) `peer_analyze.php` — API Proxy for the Frontend

**What it does**
- Serves `data/analyze-dashboard` to the frontend (PeerScan table & Connectivity pages).
- Normalizes legacy payloads if needed.
- Sends correct headers (`Content-Type: application/json`, `Cache-Control: no-store`).
- Returns JSON error if the dataset is unavailable.

**Minimal implementation**
```php
<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$path = __DIR__ . '/../data/analyze-dashboard';
if (!is_readable($path)) {
  http_response_code(503);
  echo json_encode(['error' => 'unavailable']);
  exit;
}
echo file_get_contents($path);
```
```

---

## 📊 Data Model — `data/analyze-dashboard`

**Top‑level**
```json
{
  "last_updated": "2025-08-27T17:00:43.036Z",
  "chains": {
    "Story Protocol Aeneid": {
      "all": [ /* ... full peers ... */ ],
      "top_by_region": {
        "EU/UK": [ /* ranked peers */ ],
        "America/Canada": [ /* ranked peers */ ]
      }
    }
  }
}
```

**Peer object (typical fields)**
- `peer` — `"node_id@ip:port"`
- `moniker` — validator/peer name
- `version` — software version (if available)
- `uptime` — integer `%` (0–100), last ~30 days
- `latency_avg_ms` — global average latency (rounded, `null` if < 1 ms or missing)
- `latency_by_region` — `{ "EU/UK": { "avg_ms": 23, "score": 100, "n": 14 }, ... }`
- `score` — global score (0–100)
- `location` — `{ "country": "Germany", "region": "Bavaria", "city": "Nuremberg", "isp": "..." }`

> **Region normalization**: any incoming `Americas/*` label is normalized to `America/*`.

---

## 📐 Scoring & Evaluation Strategy

### 🧮 Score Computation Details

#### 1) Latency Tier (0–100 points)
| Round-trip latency (ms) | Latency points |
| --- | --- |
| `< 50` | **100** |
| `< 100` | **80** |
| `< 200` | **60** |
| `< 300` | **40** |
| `≥ 300` or timeout | **20** (or 0 if unreachable) |

*Measurement*: single ICMP ping or request‑based inference per probe. Latencies `< 1 ms` are discarded.

#### 2) Uptime Ratio (0–100 %)
```
uptime % = (# snapshots the peer appears in) / (total snapshots) × 100
```
- Unique per snapshot: a peer counts **once** per snapshot file.
- Rolling window: ~30 days (older files pruned).

#### 3) Final Score (0–100)
A 0–100 scale that combines latency class and uptime share.
- 100 is only achievable with **top latency** and **perfect uptime**.
- Both very stable high‑latency and very fast but flaky peers are penalized.

---

## 🌍 Geolocation Strategy

We use `http://ip-api.com/json/{ip}` to classify nodes by continent, country, region/state, city, and ISP. This enables distribution analysis and policy‑aware peer selection.

---

## 🔗 Top‑N per Region & Connectivity UI

We publish **Top‑N (default 12) peers per region** to strike a balance between **latency**, **uptime**, **diversity** (different providers/locations), and **redundancy**.  
Within each region, we rank candidates by **regional score** (desc), then **latency** (asc), then **uptime** (desc), and finally **moniker** (asc) as a tie‑breaker.

You can inspect the full dataset and how each peer performs in the **PeerScan** table:
- **PeerScan:** `peer-monitor.php` (sortable columns, filters, CSV)
- **Connectivity:** region cards (copy‑to‑clipboard lists for `persistent_peers` in `config.toml`)

---

## 🔐 Reliability & Fault Handling

- Per‑request timeouts (`AbortController`, default 15 s).
- All failures logged to `logs/systemd.err.log`.
- Skips malformed validator files or unresponsive RPCs.
- Throttled IP lookups (`sleep 1500 ms`) to avoid rate‑limits.

---

## 🚀 Deployment (Suggested Cron)

```cron
# Discovery every 4h
0 */4 * * * /usr/bin/node /opt/peer-monitor/server-peers.js >> /var/log/peer-monitor.log 2>&1

# Analysis 5 min later
5 */4 * * * /usr/bin/node /opt/peer-monitor/analyze_peers.js >> /var/log/peer-monitor.log 2>&1
```

Ensure your web server (e.g., Nginx/Apache/PHP‑FPM) can read `data/analyze-dashboard` and that `peer_analyze.php` returns JSON with `Cache-Control: no-store`.

---

## 🛣 Roadmap

- Multi‑region probes (truly distributed latency measurements)
- Advanced scoring with jitter/variance and recentness weighting
- CLI summaries and Prometheus/Grafana exporters
- Web GeoMap and richer drill‑downs

---

## ✅ Built for Infra Teams

Designed for validators and infrastructure teams who need to:
- Evaluate peer stability and performance
- Select low‑latency, high‑availability peers
- Maintain a healthy gossip layer in Cosmos SDK chains

Maintained with 🛰️ by **Cumulo Pro** — https://cumulo.pro
