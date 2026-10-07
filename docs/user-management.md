# Creating users and managing company access

Open **Settings → Users & roles**, or `/settings/users`, after selecting a company. The page requires the company's `users.manage` permission; Owners and Administrators have it by default.

## Create an account

1. Click **New user**.
2. Enter their name, email, password, confirmation and company role.
3. Click **Create user**. Passwords require at least 12 characters with letters and numbers and are stored as hashes.
4. The user logs in with that email/password and verifies their email. Development email links are written to `storage/logs/laravel.log`; deployed environments use the configured mail transport.
5. After verification, they select the company and open a permitted feature. For example, Employees open Expenses and Sales Managers open Invoices.

The account is attached only to the active company. Existing global accounts cannot be overwritten or attached through account creation; invite their email and let them accept membership. Platform administration is a separate trusted-server privilege and is never assigned by the user-creation form.

## Assign roles and manage activation

Each member row contains a role selector, **Active** checkbox and **Save access** button. Changing roles changes permissions in this company only. Deactivation removes company access while preserving documents, audit attribution and financial history. Reactivation respects subscription member limits. The last active Owner cannot be deactivated or demoted.

Available roles are Owner, Administrator, Accountant, Sales Manager, Employee and Read-only User. The Owner retains all company permissions. These predefined roles are customized with permission checkboxes; creating additional named role templates is not part of this screen.

## Customize role permissions

In **Company role permissions**, select a role, check/uncheck permissions and click **Save company permissions**. These changes affect all members assigned to that role in this company. Other companies' role settings remain separate. The backend enforces these permissions on protected actions; navigation follows the same permissions.

## Invitations

Enter an email and role under **Invite an existing or new user**, then click **Create invitation**. Share the generated private link with that recipient. They register or log in with that email, verify it and accept the link. Invitations expire in seven days, can be accepted once, and can be revoked from the pending-invitations list. The system rejects duplicate pending invitations and invitations to existing members.

There is no automatic invitation-email delivery currently; the page provides a private link to share. Account verification emails are delivered through the configured Laravel mail transport. Password recovery is available from the login screen.

## Implementation and verification

Backend: `CompanyUserController`, `CreateCompanyUserRequest`, `MembershipService`, `CompanyPermissions`, `CompanyHome` and permission-protected company routes. Frontend: `Settings/Users.vue`, `CreateCompanyUserForm`, `MemberAccessForm` and the shared `RolePermissionEditor`.

`tests/Feature/CompanyUserManagementTest.php` covers account hashing/verification, uniqueness, validation, authorization, tenant isolation, activation, last-owner protection, plan limits, invitation revocation and role-aware company navigation. `tools/browser-users-check.mjs` uses the dedicated local Browser Studio fixture to verify account creation, persisted access/permission edits and responsive layout. It leaves its test member inactive and restores the tested role permission.
