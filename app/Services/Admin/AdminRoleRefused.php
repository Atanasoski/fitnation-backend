<?php

namespace App\Services\Admin;

use RuntimeException;

/**
 * Admins::revoke() would lock the panel: your own super-admin role, or the
 * last super admin. The message is fit to show the super admin.
 */
final class AdminRoleRefused extends RuntimeException {}
