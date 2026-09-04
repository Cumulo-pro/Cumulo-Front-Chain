# 🔌 check_d : Public Resources & Chain Coverage

## Live API Endpoints

| Checker Type | Public URL |
|---|---|
| Tendermint / Cosmos RPC | [`https://aggregate-rpcs.cumulo.com.es/aggregate-rpcs`](https://aggregate-rpcs.cumulo.com.es/aggregate-rpcs) |
| EVM JSON-RPC | [`https://aggregate-evm-rpcs.cumulo.com.es/aggregate-evm`](https://aggregate-evm-rpcs.cumulo.com.es/aggregate-evm) |
| Cosmos REST API | [`https://aggregate-apis.cumulo.com.es/aggregate-apis`](https://aggregate-apis.cumulo.com.es/aggregate-apis) |

---

## Smart Endpoint Selector (JSON, ranked)

Same data as above, pre-ranked by health, reliability, and smoothed latency. See the main [README](./README.md#-smart-endpoint-selector) for the full ranking criteria.

| Endpoint | Example |
|---|---|
| Best RPC | `https://aggregate-rpcs.cumulo.com.es/best-rpc?chain=Celestia+mainnet` |
| Ranked RPC list | `https://aggregate-rpcs.cumulo.com.es/rank-rpcs?chain=Celestia+mainnet` |
| Best REST API | `https://aggregate-apis.cumulo.com.es/best-api?chain=Celestia+mainnet` |
| Ranked REST API list | `https://aggregate-apis.cumulo.com.es/rank-apis?chain=Celestia+mainnet` |

Add `&region=US`, `&region=EU`, or `&region=CA` to any of the above to rank by that region's latency instead of the global average. `chain` must match a name from `chains.json` exactly, URL-encoded.

---

## Monitored Chains

### Tendermint / Cosmos RPC (`/aggregate-rpcs`)

| Chain | Network | Dashboard |
|---|---|---|
| Celestia | Mainnet | [cumulo.pro/services/celestia/rpcscan.php](https://cumulo.pro/services/celestia/rpcscan.php) |
| Celestia | Testnet (Mocha) | [cumulo.pro/services/celestia_mocha/rpcscan.php](https://cumulo.pro/services/celestia_mocha/rpcscan.php) |
| Cosmos Hub | Mainnet | [cumulo.pro/services/cosmos/rpcscan.php](https://cumulo.pro/services/cosmos/rpcscan.php) |
| Cosmos Hub | Testnet | [cumulo.pro/services/cosmos_testnet/rpcscan.php](https://cumulo.pro/services/cosmos_testnet/rpcscan.php) |
| XRPL EVM | Mainnet | [cumulo.pro/services/xrplevm_mainnet/rpcscan.php](https://cumulo.pro/services/xrplevm_mainnet/rpcscan.php) |
| XRPL EVM | Testnet | [cumulo.pro/services/xrplevm/rpcscan.php](https://cumulo.pro/services/xrplevm/rpcscan.php) |
| Gnoland | Testnet (Topaz-1) | [cumulo.pro/services/gnoland_testnet/rpcscan.php](https://cumulo.pro/services/gnoland_testnet/rpcscan.php) |
| Dymension | Mainnet | - |
| Monad | Testnet | - |
| Monad | Mainnet | - |

> Gnoland has no REST/API endpoint in its validator list (`api` field is never populated), so it has no `apiscan.php` and doesn't appear in the API table below.

### Cosmos REST API (`/aggregate-apis`)

| Chain | Network | Dashboard |
|---|---|---|
| Celestia | Mainnet | [cumulo.pro/services/celestia/apiscan.php](https://cumulo.pro/services/celestia/apiscan.php) |
| Celestia | Testnet (Mocha) | [cumulo.pro/services/celestia_mocha/apiscan.php](https://cumulo.pro/services/celestia_mocha/apiscan.php) |
| Cosmos Hub | Mainnet | [cumulo.pro/services/cosmos/apiscan.php](https://cumulo.pro/services/cosmos/apiscan.php) |
| Cosmos Hub | Testnet | [cumulo.pro/services/cosmos_testnet/apiscan.php](https://cumulo.pro/services/cosmos_testnet/apiscan.php) |
| XRPL EVM | Mainnet | [cumulo.pro/services/xrplevm_mainnet/apiscan.php](https://cumulo.pro/services/xrplevm_mainnet/apiscan.php) |
| XRPL EVM | Testnet | [cumulo.pro/services/xrplevm/apiscan.php](https://cumulo.pro/services/xrplevm/apiscan.php) |
| Dymension | Mainnet | - |

### EVM JSON-RPC (`/aggregate-evm`)

| Chain | Network | Dashboard |
|---|---|---|
| XRPL EVM | Mainnet | - |
| XRPL EVM | Testnet | - |

---

## Validator List Sources

| Chain | RPC validators | API validators |
|---|---|---|
| Celestia mainnet | [`Celestia/data/validators.json`](https://raw.githubusercontent.com/Cumulo-pro/Cumulo-Front-Chain/refs/heads/main/Celestia/data/validators.json) | same file |
| Celestia testnet (Mocha) | [`Celestia/data/validators_mocha-5.json`](https://raw.githubusercontent.com/Cumulo-pro/Cumulo-Front-Chain/refs/heads/main/Celestia/data/validators_mocha-5.json) | same file |
| Cosmos mainnet | [`cumulo-cosmoshub-infra/data/validators.json`](https://raw.githubusercontent.com/Cumulo-pro/cumulo-cosmoshub-infra/refs/heads/main/data/validators.json) | same file |
| Cosmos testnet | [`cumulo-cosmoshub-infra/data/validators_testnet.json`](https://raw.githubusercontent.com/Cumulo-pro/cumulo-cosmoshub-infra/refs/heads/main/data/validators_testnet.json) | same file |
| XRPL EVM mainnet | [`xrplevmTools/data/validators_mainnet.json`](https://raw.githubusercontent.com/Cumulo-pro/xrplevmTools/refs/heads/main/data/validators_mainnet.json) | same file |
| XRPL EVM testnet | [`xrplevmTools/data/validators_testnet.json`](https://raw.githubusercontent.com/Cumulo-pro/xrplevmTools/main/data/validators_testnet.json) | same file |
| Gnoland testnet | [`cumulo-gnoland-infra/data/validators_testnet.json`](https://raw.githubusercontent.com/Cumulo-pro/cumulo-gnoland-infra/refs/heads/main/data/validators_testnet.json) | no `api` field populated, RPC only |
| All chains index | [`chains.json`](https://raw.githubusercontent.com/Cumulo-pro/Cumulo-Front-Chain/refs/heads/main/chains.json) | same file |

To add or remove a validator, edit the corresponding JSON file on GitHub. Changes are reflected within **5 minutes**. To mark an endpoint as pruned vs archival, add an `archive_rpc` field to its entry (see [Pruning / Archival Classification](./README.md#-pruning--archival-classification) in the README).

---

## Regional Agents

| Location | RPC port | API port | EVM port | Covers |
|---|---|---|---|---|
| 🇺🇸 United States (St. Louis) | 3003 | 3005 | 3004 | All chains |
| 🇪🇺 Europe (France) | 3003 | 3006* | 3004 | All chains |
| 🇨🇦 Canada | 3003 | 3005 | 3004 | All chains |

*EU API checker uses port 3006 because port 3005 is occupied by another service on that server.

---

## Version History

| Component | Version | Date | Key Changes |
|---|---|---|---|
| RPC Checker | V1 | 2024 | Initial checker, single region |
| RPC Checker | V2 | 2024 | Multi-chain grouping |
| RPC Checker | V3 | 2025 | Reliability history |
| **RPC Checker** | **V4** | **2026-05-30** | GET /status fix, reliability rename, 8s timeout, anti-overlap, aggregator cache, EU region |
| **RPC Checker** | **V4.1** | **2026-08-03** | `res.ok` check for clearer HTTP-status error detail; `reliability.json` auto-purges RPCs no longer present in any validators file; aggregator now emits a synthetic "⚠️ Config Error" row when a chain's validators JSON fails to parse entirely, instead of silently returning an empty list |
| **RPC Checker** | **V5** | **2026-09-04** | `checkedAt` / `lastSuccessAt` timestamps; `pruning` reclassified from curated `archive_rpc` data instead of an unreliable block-height heuristic; `corsEnabled` now sends an `Origin` header so it reflects real browser behavior instead of false-negatives; `smoothedAverageLatency` (EMA, anti-flapping); passive `rateLimitHint`; independent hourly `tls` certificate check, fixed to run outside the request's own try/catch so it still resolves for endpoints that are otherwise down |
| API Checker | V1 | 2024 | Initial checker with Puppeteer |
| API Checker | V2 | 2025 | Multi-region, uptime history |
| **API Checker** | **V4** | **2026-05-31** | Puppeteer removed, fetch simple, reliability rename, latency null on error, anti-overlap, aggregator cache, EU region |
| **API Checker** | **V5** | **2026-09-04** | Same set of additions as RPC Checker V5 above, minus `pruning` (not applicable to REST) |
| **Aggregator** | **V2** | **2026-09-04** | Merges all V5 fields across regions (most-recent-with-fallback for point-in-time fields, averaged for latency); new `/rank-rpcs`, `/best-rpc`, `/rank-apis`, `/best-api` routes (Smart Endpoint Selector) |
