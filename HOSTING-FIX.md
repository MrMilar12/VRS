# Hosted assistant and requisition fixes

The private hosting package includes the application code and `config/ollama.local.php`, configured for direct Ollama Cloud with `gpt-oss:20b`. Treat the package as private because it contains the API key. It is not intended for a public repository.

## Install

1. Back up the hosted application files using your hosting file manager or deployment tool.
2. Extract the package into the existing VRS application directory, where `index.php` and `actions.php` live. Replace the application files, including `config/ollama.local.php`.
3. Preserve your existing `config/local.php`, `storage/`, and uploaded files. The package does not contain your database configuration, databases, authentication keys, or uploads. No SQL import is required.
4. Refresh the page. If the old wording remains, reset PHP's opcode cache or restart PHP using your hosting control panel, then refresh again.

## Verify

- Open Booking assistant. A missing setup notice now identifies the missing API key, cURL extension, or provider setting.
- Ask “Who approves requests?” to check the Cloud connection.
- Open New requisition. Type a destination directly; search suggestions are optional.
- Submit with a return time earlier than departure. Correct the time in the restored form and submit again; the form should remain a new request until saving succeeds.
- Confirm the saved requisition has a reference such as `VR-2026-0001` and appears in the requisition list.

If saving fails, use the error reference displayed on the page to locate the corresponding `VRS save` entry in the hosting PHP error log. That entry records the database error codes; the page keeps the entered fields for correction. The older “Check for duplicate identifiers” text means the old `actions.php` is still being served.
