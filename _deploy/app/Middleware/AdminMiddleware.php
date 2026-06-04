<?php

namespace Middleware;

class AdminMiddleware
{
    public function handle(): void
    {
        if (empty($_SESSION['user_id'])) {
            session_flash('error', 'Please log in to continue.');
            redirect('/?page=login');
        }
        $user = current_user();
        if (!$user || !in_array($user['role'], ['webmaster', 'super_admin', 'support', 'inventory_manager'], true)) {
            abort(403, 'Access denied. Admin privileges required.');
        }
    }
}
