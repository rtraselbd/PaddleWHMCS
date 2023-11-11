# WHMCS Paddle Payment Gateway

## Introduction

Welcome to the documentation for the WHMCS Paddle Payment Gateway module. This module allows you to integrate Paddle as a payment gateway in WHMCS, enabling you to accept payments seamlessly.

## Table of Contents

1. [Requirements](#requirements)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Usage](#usage)
5. [Troubleshooting](#troubleshooting)
6. [Support](#support)

## Requirements

Before you begin, ensure that you have the following:

- [WHMCS](https://www.whmcs.com/) installed and configured.
- Paddle account with access to Product ID, Vendor ID / Seller ID, and Auth Code.

## Installation
1. Download [RTPaddle.zip](https://github.com/rtraselbd/PaddleWHMCS/releases/download/v1.0.0/RTPaddle.zip) file from the Release section of this repository.
2. Unzip the downloaded file (`RTPaddle.zip`) to the root directory of your WHMCS installation.
3. The module is now ready to be configured.

## Configuration

1. Log in to your WHMCS admin panel.
2. Navigate to **Setup** > **Payments** > **Payment Gateways**.
3. Find and select "[Paddle Payment Gateway]" from the list of available gateways.
4. Fill in the required fields:

   - **Product ID:** Obtain this from the Paddle Product section.
   - **Vendor ID / Seller ID:** Collect your Paddle Vendor ID / Seller ID from Paddle Developer Tools.
   - **Auth Code:** Find the Paddle Auth Code in Paddle Developer Tools under Authentication.
   - **Fee (%):** (Optional) Add a gateway fee if needed.
   - **Extra Fee (USD):** (Optional) Add an additional fee in USD if needed.
   - **Exchange Rate:** Define the exchange rate for USD to your local currency (e.g., 1 USD = ? BDT).
   - **Sandbox Mode:** Tick this box to run the gateway in Sandbox Mode for testing.

5. Save the changes.

## Usage

Once configured, the Paddle payment gateway will be available for your clients during the checkout process. Ensure that you have tested the gateway thoroughly in both live and sandbox modes.

Thank you for choosing our WHMCS Paddle Payment Gateway!