# 🔌 check_d — Public Resources & Chain Coverage

## Live API Endpoints

| Checker Type | Public URL |
|---|---|
| Tendermint / Cosmos RPC | [`https://aggregate-rpcs.cumulo.com.es/aggregate-rpcs`](https://aggregate-rpcs.cumulo.com.es/aggregate-rpcs) |
| EVM JSON-RPC | [`https://aggregate-evm-rpcs.cumulo.com.es/aggregate-evm`](https://aggregate-evm-rpcs.cumulo.com.es/aggregate-evm) |
| Cosmos REST API | [`https://aggregate-apis.cumulo.com.es/aggregate-apis`](https://aggregate-apis.cumulo.com.es/aggregate-apis) |

---

## Monitored Chains

### Tendermint / Cosmos RPC (`/aggregate-rpcs`)

| Chain | Network | Dashboard |
|---|---|---|
| Celestia | Mainnet | [cumulo.pro/services/celestia_v2/rpcscan](https://cumulo.pro/services/celestia_v2/rpcscan) |
| Celestia | Testnet (Mocha) | [cumulo.pro/services/celestia_mocha/rpcscan](https://cumulo.pro/services/celestia_mocha/rpcscan) |
| Cosmos Hub | Mainnet | [cumulo.pro/services/cosmos/rpcscan](https://cumulo.pro/services/cosmos/rpcscan) |
| Cosmos Hub | Testnet | [cumulo.pro/services/cosmos_testnet/rpcscan](https://cumulo.pro/services/cosmos_testnet/rpcscan) |
| XRPL EVM | Mainnet | [cumulo.pro/services/xrplevm_mainnet/rpcscan](https://cumulo.pro/services/xrplevm_mainnet/rpcscan) |
| XRPL EVM | Testnet | [cumulo.pro/services/xrplevm/rpcscan](https://cumulo.pro/services/xrplevm/rpcscan) |
| Story Protocol | Aeneid (testnet) | — |
| Story Protocol | Mainnet | — |
| Dymension | Mainnet | — |
| Monad | Testnet | — |
| Monad | Mainnet | — |

### Cosmos REST API (`/aggregate-apis`)

| Chain | Network | Dashboard |
|---|---|---|
| Celestia | Mainnet | [cumulo.pro/services/celestia_v2/apiscan](https://cumulo.pro/services/celestia_v2/apiscan) |
| Celestia | Testnet (Mocha) | [cumulo.pro/services/celestia_mocha/apiscan](https://cumulo.pro/services/celestia_mocha/apiscan) |
| Cosmos Hub | Mainnet | [cumulo.pro/services/cosmos/apiscan](https://cumulo.pro/services/cosmos/apiscan) |
| Cosmos Hub | Testnet | [cumulo.pro/services/cosmos_testnet/apiscan](https://cumulo.pro/services/cosmos_testnet/apiscan) |
| XRPL EVM | Mainnet | [cumulo.pro/services/xrplevm_mainnet/apiscan](https://cumulo.pro/services/xrplevm_mainnet/apiscan) |
| XRPL EVM | Testnet | [cumulo.pro/services/xrplevm/apiscan](https://cumulo.pro/services/xrplevm/apiscan) |
| Story Protocol | Mainnet | — |
| Dymension | Mainnet | — |

### EVM JSON-RPC (`/aggregate-evm`)

| Chain | Network | Dashboard |
|---|---|---|
| XRPL EVM | Mainnet | — |
| XRPL EVM | Testnet | — |
| Story Protocol | Mainnet | — |

---

## Validator List Sources

| Chain | RPC validators | API validators |
|---|---|---|
| Celestia mainnet | [`Celestia/data/validators.json`](https://raw.githubusercontent.com/Cumulo-pro/Cumulo-Front-Chain/refs/heads/main/Celestia/data/validators.json) | same file |
| Celestia testnet | [`Celestia/data/validators_testnet.json`](https://raw.githubusercontent.com/Cumulo-pro/Cumulo-Front-Chain/refs/heads/main/Celestia/data/validators_testnet.json) | same file |
| Cosmos mainnet | [`cumulo-cosmoshub-infra/data/validators.json`](https://raw.githubusercontent.com/Cumulo-pro/cumulo-cosmoshub-infra/refs/heads/main/data/validators.json) | same file |
| All chains index | [`chains.json`](https://raw.githubusercontent.com/Cumulo-pro/Cumulo-Front-Chain/refs/heads/main/chains.json) | same file |

To add or remove a validator, edit the corresponding JSON file on GitHub. Changes are reflected within **5 minutes**.

---

## Regional Agents

| Location | RPC port | API port | Covers |
|---|---|---|---|
| 🇺🇸 United States (St. Louis) | 3003 | 3005 | All chains |
| 🇪🇺 Europe (France) | 3003 | 3006* | All chains |
| 🇨🇦 Canada | 3003 | 3005 | All chains |

*EU API checker uses port 3006 because port 3005 is occupied by another service on that server.

---

## Version History

| Component | Version | Date | Key Changes |
|---|---|---|---|
| RPC Checker | V1 | 2024 | Initial checker, single region |
| RPC Checker | V2 | 2024 | Multi-chain grouping |
| RPC Checker | V3 | 2025 | Reliability history |
| **RPC Checker** | **V4** | **2026-05-30** | GET /status fix, reliability rename, 8s timeout, anti-overlap, aggregator cache, EU region |
| API Checker | V1 | 2024 | Initial checker with Puppeteer |
| API Checker | V2 | 2025 | Multi-region, uptime history |
| **API Checker** | **V4** | **2026-05-31** | Puppeteer removed, fetch simple, reliability rename, latency null on error, anti-overlap, aggregator cache, EU region |
