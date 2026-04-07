<?php

namespace Tests\Feature\Auth;

// Password reset is obsolete with magic-link passwordless authentication.
// These tests are intentionally empty.
// See: app/Http/Controllers/Web/Auth/MagicLinkController.php for the new flow.
use Tests\TestCase;

class ResetPasswordTest extends TestCase {}
