# 🔌 check_d — Public Resources & Chain Coverage

## Live API Endpoints

| Checker Type | Public URL |
|---|---|
| Tendermint / Cosmos RPC | [`https://aggregate-rpcs.cumulo.com.es/aggregate-rpcs`](https://aggregate-rpcs.cumulo.com.es/aggregate-rpcs) |
| EVM JSON-RPC | [`https://aggregate-evm-rpcs.cumulo.com.es/aggregate-evm`](https://aggregate-evm-rpcs.cumulo.com.es/aggregate-evm) |
| REST API | [`https://aggregate-apis.cumulo.com.es/aggregate-apis`](https://aggregate-apis.cumulo.com.es/aggregate-apis) |

---

## Monitored Chains

### Tendermint / Cosmos RPC (`/aggregate-rpcs`)

| Chain | Network | Dashboard |
|---|---|---|
| Celestia | Mainnet | [cumulo.pro/services/celestia_v2/rpcscan](https://cumulo.pro/services/celestia_v2/rpcscan) |
| Celestia | Testnet (Mocha) | [cumulo.pro/services/celestia_mocha/rpcscan](https://cumulo.pro/services/celestia_mocha/rpcscan) |
| XRPL EVM | Testnet | — |
| XRPL EVM | Mainnet | — |
| Story Protocol | Aeneid (testnet) | — |
| Story Protocol | Mainnet | — |
| Dymension | Mainnet | — |
| Monad | Testnet | — |
| Monad | Mainnet | — |

### EVM JSON-RPC (`/aggregate-evm`)

| Chain | Network | Dashboard |
|---|---|---|
| XRPL EVM | Mainnet | — |
| XRPL EVM | Testnet | — |
| Story Protocol | Mainnet | — |

### REST API (`/aggregate-apis`)

| Chain | Network | Dashboard |
|---|---|---|
| XRPL EVM | Mainnet | — |
| XRPL EVM | Testnet | — |

---

## Validator List Sources

All endpoint lists are maintained in the public GitHub repository and updated by the Cumulo team:

| Chain | File |
|---|---|
| Celestia mainnet | [`Celestia/data/validators.json`](https://raw.githubusercontent.com/Cumulo-pro/Cumulo-Front-Chain/refs/heads/main/Celestia/data/validators.json) |
| Celestia testnet | [`Celestia/data/validators_testnet.json`](https://raw.githubusercontent.com/Cumulo-pro/Cumulo-Front-Chain/refs/heads/main/Celestia/data/validators_testnet.json) |
| All chains index | [`chains.json`](https://raw.githubusercontent.com/Cumulo-pro/Cumulo-Front-Chain/refs/heads/main/chains.json) |

To add or remove a validator from monitoring, submit a PR editing the corresponding JSON file. Changes are reflected within **5 minutes**.

---

## Regional Agents

| Location | IP | Covers |
|---|---|---|
| 🇺🇸 United States (St. Louis) | 148.72.141.245 | All chains |
| 🇪🇺 Europe (France) | 92.204.168.57 | All chains |
| 🇨🇦 Canada | 51.79.78.121 | All chains |

---

## Version History

| Version | Date | Key Changes |
|---|---|---|
| V1 | 2024 | Initial RPC checker, single region |
| V2 | 2024 | Multi-chain grouping per chain |
| V3 | 2025 | Added uptime/reliability history (`uptime.json`) |
| **V4** | **2026-05-30** | `GET /status` fix, reliability metric rename, 8s timeout, anti-overlap flag, aggregator cache, EU region added |
