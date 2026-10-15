# Operational Protocols

This directory contains the standard operating protocols Cumulo follows for recurring infrastructure operations.

Where `postmortem/` documents what happened during a specific incident, `protocols/` documents **how we do things every time**, so that critical operations are repeatable, auditable, and safe regardless of who executes them or under what time pressure.

Each protocol documents:
- When it applies
- Safety principles that must never be broken
- Step-by-step procedure with verification commands
- Known pitfalls learned from real operations

> **Note:** these documents are prepared for public GitHub disclosure. Hosts are referred to by generic roles (*origin*, *destination*, *affected host*). No hostnames, IPs, usernames, or datacenter references are included.

## Index

| Protocol | Applies to |
|---|---|
| [Validator migration](validator-migration.md) | Moving a CometBFT / Cosmos SDK validator to a different host |
| [Planned hardware maintenance](planned-maintenance.md) | Provider-scheduled downtime of one or more hosts (relocation, hardware swap, network work) |
