# 🛰️ Cumulo — Technical Incident Report
## Network Degradation Event Affecting Story Protocol Validator

**Incident Date:** 11 December 2025  
**Impact Window:** **02:00–02:08 CET** (01:00–01:08 UTC)  
**Location of Affected Infrastructure:** **St. Louis, USA**  
**Severity:** Medium  
**Affected Services:**  
- **Story Protocol validator node**

---

## 1. Summary

In the early hours of **11 December 2025**, the Cumulo-operated **Story Protocol validator** located in **St. Louis (USA)** experienced a **temporary consensus stall** lasting several minutes.

During this window, the validator:
- Stopped receiving valid block proposals for multiple rounds
- Logged repeated proposal and round timeout events
- Momentarily lost the ability to participate in consensus

No internal infrastructure issues were detected:
- No network interface failures
- No firewall interference
- No service restarts
- No kernel-level link events

The incident resolved automatically once upstream connectivity stabilized.

---

## 2. Timeline (CET)

### 01:58–02:00 CET
Validator operating normally and producing blocks as expected.

### 02:00 CET
Initial symptoms detected:
- Incomplete or rejected block proposals
- Consensus round timeouts
- Temporary inability to prevote / precommit

### 02:00–02:05 CET
Clear stall window:
- Repeated proposal processing failures
- Missing gossip data
- Multiple NewHeight rounds without progress

### 02:05–02:08 CET
Gradual recovery:
- Complete proposals received again
- Consensus resumes normally

### After 02:10 CET
Validator fully operational with no manual intervention.

---

## 3. Impact Assessment

- Temporary loss of consensus participation on Story Protocol
- Several consecutive rounds affected
- **No slashing**
- **No resync required**
- **Automatic recovery**

---

## 4. Investigation Findings

### Hardware & Kernel
- Network interface remained **UP / LOWER_UP**
- No RX/TX errors, carrier drops, or resets
- No MTU, FCS, or alignment errors detected

### System & Services
- Story service remained running throughout the incident
- No systemd restarts
- No CPU, memory, or disk exhaustion

### Firewall
- Only standard multicast traffic was blocked
- No Story-related ports were affected

### Story Consensus Logs
Logs show symptoms consistent with **upstream network degradation**:
- Late or incomplete proposal delivery
- Round timeouts
- Rejected proposals due to missing data

---

## 5. Root Cause

**External Internet Routing Degradation — Regional (St. Louis, USA)**

The incident was caused by a short-lived but severe degradation in upstream Internet routing or congestion affecting the region.

Because:
- Physical connectivity remained intact
- No local faults were detected
- Consensus failed only due to missing or delayed gossip messages
- Recovery occurred immediately once routing stabilized

This class of event does not leave traces on the local server, as the physical link never went down.

---

## 6. Corrective Actions

### Completed
- Full system and network audit
- Consensus log correlation
- Hardware and firewall validation

### Recommended Improvements
1. External latency and packet-loss monitoring for the St. Louis region
2. Automated alerts for consensus round timeouts
3. Optional regional redundancy for validator infrastructure

---

## 7. Preventive Enhancements

- Deploy gossip propagation health checks
- Track proposal delivery latency metrics
- Improve dashboards with round-level visibility
- Maintain historical baselines for regional connectivity

---

## 8. Final Statement

This incident was the result of an **external regional network degradation** impacting the Story validator’s ability to receive consensus data.

Cumulo infrastructure operated correctly and recovered automatically once network conditions normalized. No long-term impact remains.

---

*Prepared by Cumulo Infrastructure Team*
