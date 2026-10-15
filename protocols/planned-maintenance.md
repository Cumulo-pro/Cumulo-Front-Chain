# 🛠️ Protocol: Planned Hardware Maintenance

**Applies to:** provider-scheduled, service-affecting downtime of one or more hosts (physical relocation, hardware replacement, network work)
**Goal:** no validator signing affected, clean shutdown of every service, unattended recovery when the window ends
**Last applied:** October 2026 (two hosts, physical relocation within the same facility, 2-hour window)

---

## 1. Principles

1. **No mainnet validator rides through the window.** Every mainnet signing key on an affected host is either moved beforehand or confirmed covered by active redundancy.
2. **Proof over assumption.** "This node doesn't sign" is verified by voting power and by checking key addresses against the live validator set, never inferred from a spreadsheet or a service name.
3. **We shut down, the provider doesn't.** A provider hard power-off risks RAID resyncs and database corruption. Services are stopped in order and the host is powered off by us before the window starts.
4. **The operator does not need to be awake.** Shutdown is scheduled, power-on after the work is automatic, and recovery checks are scripted in advance.

---

## 2. Checklist

### Phase 1. Assess (as soon as the notice arrives)

- [ ] Convert the window to local time and note it (watch DST).
- [ ] List **every** affected host in the notice, not only the one expected.
- [ ] For each affected host, inventory running services and containers:
  ```bash
  systemctl list-units --type=service --state=running --no-pager --no-legend | awk '{print $1}'
  docker ps --format 'table {{.Names}}\t{{.Image}}\t{{.Status}}'; docker compose ls
  ```
- [ ] Cross-check the inventory against the internal node registry. Anything running that the registry marks as decommissioned or moved is investigated **immediately**, not at shutdown time.
- [ ] Find every signing key on the host (search every mount point explicitly):
  ```bash
  sudo find / /home -xdev -name 'priv_validator_key.json' -not -path '*/go/pkg/mod/*' 2>/dev/null | while read f; do
    cfg=$(dirname "$f")/config.toml
    echo "$f | addr=$(sudo jq -r .address "$f") | $(sudo grep -E '^priv_validator_laddr' "$cfg" 2>/dev/null)"
  done
  ```
- [ ] For each running node, check voting power:
  ```bash
  <daemon> status --home <home> 2>&1 | jq -r '.validator_info.voting_power'
  ```
- [ ] Where `status` does not answer (client points to another port), check the key addresses directly against the chain's validator set:
  ```bash
  curl -s "<rpc>/validators?per_page=200" | jq -r '.result.validators[].address' | grep -E '<ADDR1>|<ADDR2>' || echo "none in set"
  ```
- [ ] Classify each service: **mainnet validator signing**, **covered by redundancy**, **testnet validator**, **RPC / archive / snapshot**, **auxiliary**.

### Phase 2. Protect validators

- [ ] Estimate the jail margin for each mainnet validator: `(1 − min_signed_per_window) × signed_blocks_window × block_time`. Compare with total downtime (shutdown + window + boot + catch-up).
- [ ] Mainnet validators without comfortable margin, or where we do not want to depend on margin, are migrated using the [validator migration protocol](validator-migration.md) before the window.
- [ ] Redundant validators (several active-active instances sharing the same key by design, e.g. Starknet attestation) are confirmed healthy on the hosts that stay up.
- [ ] Any stray instance that should not be running (old copy of a moved node) is stopped and removed now, since two instances with the same identity is an equivocation risk regardless of the maintenance.

### Phase 3. Prepare shutdown and recovery

- [ ] Write a shutdown script per host that stops services **in order** and powers off, logging everything:
  1. auxiliary services (collectors, checkers, agents)
  2. Docker Compose stacks with `docker compose down` (databases close cleanly)
  3. chain daemons and validators
  4. web server and exporters
  5. `sync` and `systemctl poweroff`
- [ ] Syntax-check it: `bash -n <script> && echo OK`.
- [ ] Confirm the host clock and timezone: `timedatectl | grep 'Time zone'; date -u`.
- [ ] Schedule it **before** the window (30 min margin used), in UTC:
  ```bash
  sudo systemd-run --unit=pw-shutdown --on-calendar='YYYY-MM-DD HH:MM:SS UTC' /bin/bash <script>
  systemctl list-timers --all | grep pw-shutdown
  ```
- [ ] Set the BMC power restore policy so the host boots on its own when power returns after the work:
  ```bash
  sudo ipmitool chassis status | grep -i 'power restore'
  sudo ipmitool chassis policy always-on
  ```
  `previous` is not enough: the host was off when power was cut, so it would stay off.
- [ ] Confirm out-of-band (IPMI) access works, as a manual fallback.
- [ ] Optionally ask the provider in the ticket to power the hosts on after the work.
- [ ] Write a post-boot check script per host (see Phase 5).
- [ ] Decide on alerting: silence, or let RPC-down alerts fire. If every monitored chain has a public fallback node, only endpoint-down alerts fire and validator monitoring keeps working.
- [ ] Register the maintenance in the activity tracker with `status: in_progress`.

### Phase 4. During the window

- [ ] Nothing to do. The shutdown runs on schedule.

### Phase 5. Recovery

- [ ] Run the post-boot check on each host:
  - uptime, RAID state (`[UU]`, no unexpected resync), mounts
  - tail of the shutdown log (confirms the shutdown was clean)
  - every expected service `active`
  - every intentionally retired service still `inactive` / `disabled`
  - nodes catching up to `catching_up: false`
- [ ] Start manually whatever `docker compose down` removed and must come back (`docker compose up -d`).
- [ ] Investigate any `failed` unit: `journalctl -u <unit> -n 50 --no-pager`.
- [ ] Confirm monitoring alerts resolved.
- [ ] Remove the leftover timer: `sudo systemctl reset-failed pw-shutdown.timer`.
- [ ] Close the activity tracker entry as `completed`.

---

## 3. Execution record: October 2026 relocation

Two hosts (*Host A*, *Host B*) scheduled for physical relocation, 2-hour service-affecting window, 21:00–23:00 UTC.

### Assessment
- [x] Both affected hosts identified from the notice
- [x] Inventory of services and containers taken on both hosts
- [x] Every signing key located on both hosts (search corrected to cover `/home`, a separate volume)
- [x] Voting power checked on every running node: all `0`
- [x] Remaining key addresses checked against the live validator sets: none present
- [x] Services classified

**Host A** ran two mainnet validators (Axone, Warden), a Starknet testnet validator, a testnet signer reached through a remote signer, and RPC / archive / snapshot / backup services.
**Host B** ran one instance of the Starknet mainnet validator, a backup consensus node with signing disabled, and RPC / archive / snapshot services.

### Validator protection
- [x] Jail margin evaluated: decision taken not to depend on it for mainnet
- [x] **Axone mainnet** migrated to destination 1; signing verified; origin disabled and key removed
- [x] **Warden mainnet** migrated to destination 2 (separate host, to avoid concentrating mainnet validators); signing verified; origin disabled and key removed; price oracle sidecar intentionally not redeployed and disabled in config
- [x] Origin hosts re-checked for any copy of either key, across all mount points: none found
- [x] Starknet mainnet confirmed covered by its two other active-active instances on unaffected hosts
- [x] A stray testnet sequencer instance still running on Host A after being moved elsewhere was found during the inventory and stopped with `docker compose down`

### Shutdown and recovery preparation
- [x] Ordered shutdown script written and syntax-checked on both hosts
- [x] Host clocks confirmed on UTC
- [x] Shutdown scheduled for 20:30 UTC on both hosts (30 min before the window)
- [x] BMC power restore policy changed from `previous` to `always-on` on both hosts
- [x] Post-boot check script prepared on both hosts
- [x] Alerting reviewed: every affected chain has a public fallback in monitoring, so only RPC-down alerts are expected
- [x] Maintenance registered in the activity tracker as `in_progress`

### Recovery
- [ ] Post-boot checks on Host A
- [ ] Post-boot checks on Host B
- [ ] Containers removed at shutdown brought back where required
- [ ] Monitoring alerts resolved
- [ ] Activity tracker entry closed as `completed`

---

## 4. Lessons captured

| Lesson | Applied as |
|---|---|
| `find / -xdev` skips separately mounted `/home` and reports a false negative | All key searches list mount points explicitly |
| A node's own `status` may not answer if its client config points elsewhere | Fall back to checking key addresses against the validator set |
| The node registry can drift from reality | Inventory is cross-checked against the registry, discrepancies acted on immediately |
| `docker compose down` removes containers, so they do not return on boot | Recovery checklist lists stacks to bring back manually |
| Power restore policy `previous` leaves a host that was shut down off | Set `always-on` before planned power interruptions |
