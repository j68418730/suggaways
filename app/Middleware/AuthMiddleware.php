<?php

namespace Middleware;

class AuthMiddleware
{
    public function handle(): void
    {
        if (empty($_SESSION['user_id'])) {
            session_flash('error', 'Please log in to continue.');
            redirect('/?page=login');
        }
    }
}
