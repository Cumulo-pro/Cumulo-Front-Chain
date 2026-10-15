# 🔐 Protocol: Validator Migration

**Applies to:** CometBFT / Cosmos SDK validators (`priv_validator_key.json` signing)
**Goal:** move a live validator to a new host with minimal downtime and **zero risk of double-signing**
**Last applied:** October 2026 (Axone mainnet, Warden mainnet)

---

## 1. Safety principles

These rules are non-negotiable. Every other step can be adapted; these cannot.

1. **The signing key is never active on two nodes at the same time.** The origin is stopped *before* the destination starts signing. Double-signing is the only failure in this procedure that is permanent (tombstoning and slashing).
2. **The origin is stopped, disabled, and its key removed**, not just stopped. A stopped-but-enabled service on a host that reboots (provider maintenance, power restore) will start signing again on its own.
3. **The destination is fully synchronized before the key moves.** `catching_up: false` is required. Key transfer is the last step, not the first.
4. **Verify by address, not by file name.** Every check that a key is present or absent compares the `address` field inside the file, so keys from other chains on the same host never cause false positives.
5. **Signing is confirmed on-chain** from the destination's own RPC, not assumed from a clean log.

---

## 2. Pre-migration assessment

### 2.1 Measure what the validator actually needs

On the origin:

```bash
# RAM (RSS) and long-run average CPU of the daemon and any sidecar
ps -eo comm,rss,%cpu,etime --sort=-rss | awk '$1 ~ /<daemon>|<sidecar>/ {printf "%s RSS %.1f GiB | CPU %s%% | up %s\n",$1,$2/1048576,$3,$4}'

# Disk footprint (data/ is what a snapshot replaces)
du -sh ~/.<home> ~/.<home>/data/* | sort -h | tail -5
```

The `ps` CPU figure is the average since process start, which is more representative than an instantaneous `top` sample.

### 2.2 Validate the destination host

```bash
nproc; uptime                 # load well below core count
free -h; swapon --show        # "available" >> validator RSS + margin, swap usage ~0
df -h / /home                 # room for data/ after snapshot restore
sudo ss -tlnp                 # ports already in use
```

Decision rules:
- **RAM:** use the `available` column, not `free`. Page cache is reclaimable. Require *available* ≥ validator RSS + 2–3 GiB margin.
- **Swap in use is a red flag.** A host already swapping under normal load will swap the validator's working set during spikes. Mainnet validators get the highest resource priority in our tiering; a swapping host is not acceptable for them.
- **Not affected by the same event.** If the migration is driven by planned maintenance, confirm the destination is not in the affected list.
- **Diversification.** Prefer not to concentrate several mainnet validators on a single host when an alternative exists.

### 2.3 Capture the exact runtime on the origin

```bash
<daemon> version --long | grep -E '^(version|commit|go):'
ldd $(which <daemon>)                      # "not a dynamic executable" = static binary
systemctl cat <daemon> [<sidecar>]
grep -nE '^(laddr|proxy_app|prometheus_listen_addr|pprof_laddr|external_address|persistent_peers|seeds) *=' ~/.<home>/config/config.toml
grep -nE '^(address|ws-address|metrics-address|minimum-gas-prices|pruning[a-z-]*) *=' ~/.<home>/config/app.toml
ls ~/.<home>                               # look for extra state dirs, e.g. wasm/
```

---

## 3. Build the destination as a non-signing full node

### 3.1 Binary: identical to the origin

Two valid paths:

- **Build from source** at the same tag, then confirm the `commit` hash matches the origin exactly.
- **Copy the origin binary** when it is statically linked (`ldd` reports *not a dynamic executable*), or when the origin build is non-reproducible (e.g. version string ends in `-dirty`). This guarantees the destination runs exactly what was signing.

If the build fails because the system Go is newer than the version the project supports, do **not** replace the system Go (other daemons on the host may depend on it). Pin the toolchain for that build only:

```bash
GOTOOLCHAIN=go<origin-version> make install
<daemon> version --long | grep -E '^(version|commit|go):'
```

For CosmWasm chains, confirm the VM library resolves:

```bash
ldd $(which <daemon>) | grep -i wasm     # must resolve, not "not found"
```

The library lives in the Go module cache; never run `go clean -modcache` on that host afterwards.

### 3.2 Init and configuration

```bash
<daemon> init <moniker> --chain-id <chain-id>
```

`init` creates a random `priv_validator_key.json`. This is expected and harmless: it is not in the validator set, and it will be overwritten at cutover.

Then either copy the origin's `genesis.json`, `app.toml`, `config.toml` (never `node_key.json` or `priv_validator_key.json`) or apply the chain's install guide, and verify the genesis checksum against the official one.

### 3.3 Ports

On multi-tenant hosts, never assume default ports. Assign a dedicated prefix per chain (e.g. `27xxx`, `28xxx`) and rewrite **only the listen lines**, so peer addresses that happen to contain the same port are not touched:

```bash
P=<prefix>; C=~/.<home>/config
sed -i -E "/^(laddr|proxy_app|prometheus_listen_addr|pprof_laddr) *=/ { s%:26656%:${P}656%; s%:26657%:${P}657%; s%:26658%:${P}658%; s%:26660%:${P}660%; s%:6060%:${P}060% }" $C/config.toml
sed -i -E "/^address *=/ { s%:1317%:${P}317%; s%:9090%:${P}090%; s%:9091%:${P}091% }" $C/app.toml
sed -i "s%^external_address = .*%external_address = \"$(curl -4 -s ifconfig.me):${P}656\"%" $C/config.toml
sed -i "s%^node = .*%node = \"tcp://localhost:${P}657\"%" $C/client.toml
```

Adjust the source ports to whatever the origin uses. Always review afterwards:

```bash
grep -nE '^(laddr|proxy_app|prometheus_listen_addr|pprof_laddr|external_address) *=' $C/config.toml
grep -nE '^(address|ws-address|metrics-address) *=' $C/app.toml
grep '^persistent_peers' $C/config.toml | grep -o '@' | wc -l   # peer count unchanged
```

`external_address` must be set explicitly: without it, a node on non-default ports cannot accept inbound peers. Open the P2P port in the firewall.

### 3.4 Sidecars

Replicate any sidecar the validator depends on (e.g. a price oracle), pointing it at the destination's new ports. If a sidecar is intentionally **not** deployed, disable it explicitly in `app.toml` (e.g. `[oracle] enabled = "false"`); leaving it enabled with nothing listening floods the log with timeouts.

### 3.5 Synchronization

Preferred order:

1. **Recent public snapshot.** Check freshness and size before downloading:
   ```bash
   curl -sI <snapshot-url> | grep -iE 'HTTP/|content-length|last-modified'
   ```
2. **State sync** against a trusted RPC. Only works if peers actually serve recent snapshots; if logs show only stale snapshots being rejected (`light block not found`), abandon it.
3. **Snapshot served from our own non-signing node** of the same chain.

Never copy data from the live validator itself, since that requires stopping it.

**Extra state not included in snapshots.** CosmWasm chains keep contract bytecode in a separate `wasm/` directory that most snapshots do not contain. Without it, the node starts and syncs normally until the first block that executes a contract, then diverges on AppHash. Contract code is immutable and append-only, so it can be copied hot from the origin:

```bash
# origin
tar -czf ~/wasm.tgz -C ~/.<home> wasm
# destination, after transfer
tar -xzf ~/wasm.tgz -C ~/.<home> && du -sh ~/.<home>/wasm
```

### 3.6 Start and confirm sync

```bash
sudo systemctl daemon-reload && sudo systemctl enable --now <daemon>
<daemon> status 2>&1 | jq '.sync_info | {latest_block_height, catching_up}'
```

Proceed only when `catching_up` is `false`.

---

## 4. Key cutover

### 4.1 Sequence

1. **Origin:** record the validator address.
   ```bash
   jq -r .address ~/.<home>/config/priv_validator_key.json
   ```
2. **Operator workstation:** download `priv_validator_key.json` from the origin. This copy is the off-server backup.
3. **Origin:** stop and disable the validator (and any sidecar), then delete the key.
   ```bash
   sudo systemctl stop <daemon> [<sidecar>] && sudo systemctl disable <daemon> [<sidecar>]
   rm ~/.<home>/config/priv_validator_key.json
   pgrep -a <daemon> || echo "stopped"
   ```
4. **Destination:** upload the key over the random one created by `init`, verify, restart.
   ```bash
   jq -r .address ~/.<home>/config/priv_validator_key.json   # must match step 1
   chmod 600 ~/.<home>/config/priv_validator_key.json
   sudo systemctl restart <daemon>
   ```

The transfer through the operator workstation naturally lets several blocks pass between stopping the origin and starting the destination, so the destination begins signing at heights the origin never touched. `priv_validator_state.json` does not need to be copied in this flow.

### 4.2 Verify signing on the destination

```bash
ADDR=$(jq -r .address ~/.<home>/config/priv_validator_key.json)
curl -s localhost:<rpc-port>/block | jq --arg a "$ADDR" '.result.block.last_commit.signatures[] | select(.validator_address==$a)'
```

`block_id_flag: 2` confirms the signature is in the commit. Repeat on a few consecutive blocks.

### 4.3 Verify the origin is clean

```bash
systemctl is-active <daemon>; systemctl is-enabled <daemon>; pgrep -a <daemon> || echo "no process"
sudo find / /home -xdev -name 'priv_validator_key*' 2>/dev/null | while read f; do
  a=$(sudo jq -r .address "$f" 2>/dev/null); [ "$a" = "<ADDRESS>" ] && echo "⚠️  COPY FOUND: $f"
done; echo "search finished"
```

Expected: `inactive`, `disabled`, no process, and only `search finished`.

**Search every filesystem explicitly.** `find / -xdev` does not descend into other mount points. On hosts where `/home` is a separate volume, a search from `/` alone silently skips every node directory and reports a false "clean".

---

## 5. Post-migration

- Update monitoring (Tenderduty, Grafana, Prometheus scrape targets) to the new node.
- Remove temporary transfer archives on both hosts and on the workstation, after storing the key backup in its usual secure location.
- When convenient, delete the origin's data directory to free space and avoid future confusion.
- Record the migration in the activity tracker (`incidents.json`) as `infrastructure_migration`.

---

## 6. Execution record: October 2026

Both migrations were triggered by a provider-scheduled maintenance on the origin host (see [planned hardware maintenance](planned-maintenance.md)).

### Axone mainnet (`axone-1`), 14 October 2026

- [x] Resource usage measured on origin (RAM is the sizing constraint)
- [x] Destination validated: ample available RAM, no swap in use, not affected by the maintenance
- [x] Binary built from source at the origin's tag; Go toolchain pinned for the build; commit hash matched
- [x] CosmWasm VM library verified to resolve
- [x] Genesis checksum verified
- [x] Pruning, gas price and peers applied; dedicated port prefix; `external_address` set
- [x] Synced from a same-day public snapshot (first provider unreachable, second used)
- [x] `wasm/` missing from the snapshot, copied from the origin before first start
- [x] `catching_up: false` confirmed
- [x] Origin stopped, disabled, key removed
- [x] Key uploaded to destination, address verified, signing confirmed (`block_id_flag: 2`)
- [x] Origin re-checked: service inactive and disabled, no copy of the key on any mount point

### Warden mainnet (`warden_8765-1`), 15 October 2026

- [x] Resource usage measured on origin
- [x] Destination chosen on a different host from Axone, validated for RAM, swap, CPU, disk and free ports
- [x] Origin binary found to be statically linked and built from a non-clean tree; copied as-is instead of rebuilt
- [x] Origin configuration copied (excluding node and validator keys); ports remapped; EVM JSON-RPC ports included
- [x] Price oracle sidecar not redeployed (chain winding down) and explicitly disabled in `app.toml`
- [x] State sync attempted: only a stale peer snapshot available and rejected; abandoned
- [x] Synced from a same-day public snapshot instead
- [x] `catching_up: false` confirmed
- [x] Origin validator and oracle stopped and disabled, key removed
- [x] Key uploaded to destination, address verified, signing confirmed (`block_id_flag: 2`)
- [x] Origin re-checked: services inactive and disabled, no copy of the key on any mount point
- [ ] Monitoring updated to the new nodes
- [ ] Origin data directories removed

---

## 7. Pitfalls observed in practice

| Pitfall | Consequence | Prevention |
|---|---|---|
| System Go newer than the project supports | Build fails in dependencies (e.g. `undefined: GoMapIterator`) | `GOTOOLCHAIN=go<version>` for that build only |
| Snapshot without `wasm/` | AppHash divergence at the first contract execution | Copy `wasm/` from the origin before starting |
| Port `sed` applied to the whole file | Peer addresses rewritten to wrong ports | Restrict substitutions to listen lines |
| State sync with only stale peer snapshots | Snapshot rejected, node idles | Fall back to a recent snapshot provider |
| Sidecar enabled but not deployed | Log flooded with connection timeouts | Disable it explicitly in `app.toml` |
| Origin stopped but left enabled | Restarts and signs after any reboot | Always `disable` and delete the key |
| `find / -xdev` with separate `/home` | False "no key found" | `find / /home -xdev ...` |
