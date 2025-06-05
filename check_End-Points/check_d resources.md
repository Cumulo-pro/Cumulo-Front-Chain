# check_d resources

## 🔌 Public Aggregator Endpoints

check_d currently exposes live monitoring data through two structured JSON APIs:

- **EVM-compatible RPCs**:  
  [`/aggregate-evm`](https://aggregate-evm-rpcs.cumulo.com.es/aggregate-evm)  
  → Returns metrics for Ethereum-like endpoints using `eth_blockNumber`.
  - chains: XRPLEVM, Story mainnet, Story aenet

- **Cosmos / Tendermint RPCs**:  
  [`/aggregate-rpcs`](https://aggregate-rpcs.cumulo.com.es/aggregate-rpcs)  
  → Returns metrics for Tendermint-style endpoints using `/status`.
  chains: XRPLEVM, Story mainnet, Story aenet
