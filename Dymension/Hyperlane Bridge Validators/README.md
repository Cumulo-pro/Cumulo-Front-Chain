# 🛰️ Dymension Hyperlane Bridge Validators — Key Generation Guide

This document provides a complete, step-by-step guide on how to generate and share the required public keys for the **Dymension ↔ Kaspa bridge validator setup** using **AWS KMS** and the **Hyperlane monorepo**.

---

## 🧩 1. Context

A subset of Dymension validators operates as **Hyperlane bridge validators** for the Dymension ↔ Kaspa connection.

Each validator must provide two public keys generated securely via AWS KMS:
- **Kaspa escrow public key** (symmetric)
- **Dymension signing public key** (asymmetric secp256k1)

---

## ⚙️ 2. Requirements

- AWS account with IAM user permissions  
- Access to AWS Console & AWS CLI  
- Rust toolchain (`cargo`)  
- Foundry (`cast`) installed for wallet operations  

---

## 🌐 3. Configure AWS CLI

Install and configure AWS CLI v2:

```bash
curl "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" -o "awscliv2.zip"
unzip awscliv2.zip
sudo ./aws/install

aws configure
# Example:
# AWS Access Key ID: AKIA...
# AWS Secret Access Key: <your-secret>
# Default region name: ap-southeast-1
# Default output format: json
```

---

## 🧱 4. Clone and Build Repository

```bash
git clone https://github.com/dymensionxyz/hyperlane-monorepo
cd hyperlane-monorepo/dymension/libs/kaspa/demo/user
sudo apt install protobuf-compiler -y
cargo build
```

---

## 🔐 5. Create AWS KMS Symmetric Key (Kaspa)

```bash
aws kms create-key --description "Hyperlane Validator Keys"   --key-usage ENCRYPT_DECRYPT   --origin AWS_KMS
```

Example output:
```
KeyId: 11d199b0-16a4-46f8-bd14-515f322b8117
Arn: arn:aws:kms:ap-southeast-1:822454584659:key/11d199b0-16a4-46f8-bd14-515f322b8117
```

Save the ARN:

```bash
echo 'arn:aws:kms:ap-southeast-1:822454584659:key/11d199b0-16a4-46f8-bd14-515f322b8117' > ~/kaspa/kaspa-kms-key-arn
```

Create a new secret for Kaspa:

```bash
aws secretsmanager create-secret   --name "validators/cumulo/hyperlane/tn/kaspa-key"   --secret-string "{}"

echo 'validators/cumulo/hyperlane/tn/kaspa-key' > ~/kaspa/kaspa-secret-path
```

---

## 🧾 6. Update KMS Key Policy

In **AWS Console → KMS → Customer managed keys → Key policy → Edit**, replace with:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "AllowRootAccountFullAccess",
      "Effect": "Allow",
      "Principal": {"AWS": "arn:aws:iam::822454584659:root"},
      "Action": "kms:*",
      "Resource": "*"
    },
    {
      "Sid": "AllowHyperlaneValidatorUseOfKey",
      "Effect": "Allow",
      "Principal": {"AWS": "arn:aws:iam::822454584659:user/hyperlane-validator-dymension"},
      "Action": [
        "kms:Encrypt",
        "kms:Decrypt",
        "kms:ReEncrypt*",
        "kms:GenerateDataKey*",
        "kms:DescribeKey"
      ],
      "Resource": "*"
    }
  ]
}
```

---

## 🪙 7. Generate Kaspa Escrow Public Key

```bash
cd ~/hyperlane-monorepo/dymension/libs/kaspa/demo/user

cargo run validator create aws   --path $(cat ~/kaspa/kaspa-secret-path)   --kms-key-id $(cat ~/kaspa/kaspa-kms-key-arn)
```

✅ Example output:
```
Kaspa Escrow Public Key: 036611f55361dee173e1a5dcffdbcf6ccd142e37ba7a8d8b32caf1bbf8d2465a97
```

---

## 🔏 8. Create AWS KMS Asymmetric Key (Dymension)

```bash
aws kms create-key   --description "Dymension Signing Key (Hyperlane)"   --key-spec ECC_SECG_P256K1   --key-usage SIGN_VERIFY
```

Example:
```
KeyId: 7ec0ebf8-cd27-46e8-aa4b-69b51b5134be
```

---

## 🧰 9. Update Dymension KMS Key Policy

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "AllowRootAccountFullAccess",
      "Effect": "Allow",
      "Principal": {"AWS": "arn:aws:iam::822454584659:root"},
      "Action": "kms:*",
      "Resource": "*"
    },
    {
      "Sid": "AllowHyperlaneValidatorUseOfKey",
      "Effect": "Allow",
      "Principal": {"AWS": "arn:aws:iam::822454584659:user/hyperlane-validator-dymension"},
      "Action": [
        "kms:Sign",
        "kms:GetPublicKey",
        "kms:DescribeKey"
      ],
      "Resource": "*"
    }
  ]
}
```

---

## 💫 10. Generate Dymension Public Key

Install Foundry and get the public key:

```bash
curl -L https://foundry.paradigm.xyz | bash
source ~/.bashrc || source ~/.profile
foundryup

AWS_KMS_KEY_ID=$(cat ~/dym/dym-kms-key-arn) cast wallet address --aws
```

✅ Example output:
```
Dymension Public Key: 0x995871E50396de955989415fbF5564AeAd0819F0
```

---

## 🧾 11. Final Result

```
Cumulo Public Keys:
Kaspa → 036611f55361dee173e1a5dcffdbcf6ccd142e37ba7a8d8b32caf1bbf8d2465a97
Dymension → 0x995871E50396de955989415fbF5564AeAd0819F0
```

---

## ✅ 12. Good Practices

- Keep both keys in the same AWS region (`ap-southeast-1`).
- Create aliases for clarity:
  ```bash
  aws kms create-alias --alias-name alias/kaspa-escrow     --target-key-id 11d199b0-16a4-46f8-bd14-515f322b8117
  aws kms create-alias --alias-name alias/dymension-signer     --target-key-id 7ec0ebf8-cd27-46e8-aa4b-69b51b5134be
  ```
- Disable or delete unused KMS keys.
- Rotate AWS access keys regularly and remove exposed credentials.

---

**Author:** Cumulo Team  
**Validator:** `hyperlane-validator-dymension`  
**Date:** November 10, 2025  
**Region:** `ap-southeast-1`
