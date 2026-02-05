<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'VITE-IT Immigration API',
    description: 'API documentation for VITE-IT Immigration',
    contact: new OA\Contact(email: 'admin@vite-it.com')
)]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'cookie',
    description: 'Laravel Sanctum cookie-based authentication. Login via POST /api/login first.'
)]
#[OA\Tag(name: 'Auth', description: 'Authentication endpoints')]
#[OA\Tag(name: 'Users', description: 'User management (admin)')]
#[OA\Tag(name: 'Profile', description: 'User profile management')]
#[OA\Tag(name: 'Roles', description: 'Role and permission management')]
#[OA\Tag(name: 'Two-Factor', description: 'Two-factor authentication')]
#[OA\Tag(name: 'Activity Logs', description: 'Activity log viewing')]
#[OA\Tag(name: 'Email Verification', description: 'Email verification')]
#[OA\Tag(name: 'Password Reset', description: 'Password reset')]
class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}
