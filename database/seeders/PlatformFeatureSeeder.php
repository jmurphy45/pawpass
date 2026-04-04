<?php

namespace Database\Seeders;

use App\Models\PlatformFeature;
use Illuminate\Database\Seeder;

class PlatformFeatureSeeder extends Seeder
{
    public function run(): void
    {
        $features = [
            ['slug' => 'add_customers',       'name' => 'Add Customers',          'description' => 'Create and manage customer profiles.', 'is_marketing' => true,  'sort_order' => 10],
            ['slug' => 'add_dogs',            'name' => 'Add Dogs',               'description' => 'Register dog profiles for customers.', 'is_marketing' => true,  'sort_order' => 20],
            ['slug' => 'customer_portal',     'name' => 'Customer Portal',        'description' => 'Self-service portal for customers.', 'is_marketing' => true,  'sort_order' => 30],
            ['slug' => 'email_notifications', 'name' => 'Email Notifications',    'description' => 'Send automated emails to customers.', 'is_marketing' => true,  'sort_order' => 40],
            ['slug' => 'basic_reporting',     'name' => 'Basic Reporting',        'description' => 'Attendance and credit reports.', 'is_marketing' => true,  'sort_order' => 50],
            ['slug' => 'sms_notifications',   'name' => 'SMS Notifications',      'description' => 'Send SMS alerts to customers.', 'is_marketing' => true,  'sort_order' => 60],
            ['slug' => 'financial_reports',   'name' => 'Financial Reports',      'description' => 'Revenue, payout and financial analytics.', 'is_marketing' => true,  'sort_order' => 70],
            ['slug' => 'weekly_daily_payouts','name' => 'Weekly & Daily Payouts', 'description' => 'Faster Stripe payout schedules.', 'is_marketing' => true,  'sort_order' => 80],
            ['slug' => 'custom_branding',     'name' => 'Custom Branding',        'description' => 'Your logo and colors throughout the app.', 'is_marketing' => true,  'sort_order' => 90],
            ['slug' => 'pwa',                 'name' => 'Mobile App (PWA)',       'description' => 'Installable mobile app for customers.', 'is_marketing' => true,  'sort_order' => 100],
            ['slug' => 'white_label',         'name' => 'White Label',            'description' => 'Remove all PawPass branding.', 'is_marketing' => true,  'sort_order' => 110],
            ['slug' => 'unlimited_staff',     'name' => 'Unlimited Staff',        'description' => 'No cap on staff accounts.', 'is_marketing' => true,  'sort_order' => 120],
            ['slug' => 'priority_support',    'name' => 'Priority Support',       'description' => 'Dedicated support with fast response times.', 'is_marketing' => true,  'sort_order' => 130],
            ['slug' => 'recurring_checkout',       'name' => 'Recurring Checkout',           'description' => 'Charge saved cards automatically at check-in.',             'is_marketing' => true,  'sort_order' => 140],
            ['slug' => 'vaccination_management',   'name' => 'Vaccination Management',       'description' => 'Create vaccination requirements and enforce compliance.',     'is_marketing' => true,  'sort_order' => 150],
            ['slug' => 'advanced_credit_ops',      'name' => 'Advanced Credit Operations',   'description' => 'Goodwill credits, corrections, and dog-to-dog transfers.',   'is_marketing' => false, 'sort_order' => 160],
            ['slug' => 'boarding',                 'name' => 'Boarding & Reservations',      'description' => 'Kennel reservations, report cards, and occupancy tracking.', 'is_marketing' => true,  'sort_order' => 170],
            ['slug' => 'addon_services',           'name' => 'Add-on Services',              'description' => 'Charge customers per-service add-ons at check-in.',          'is_marketing' => true,  'sort_order' => 180],
            ['slug' => 'broadcast_notifications',  'name' => 'Broadcast Notifications',      'description' => 'Send announcements to all customers at once.',               'is_marketing' => true,  'sort_order' => 190],
            ['slug' => 'auto_replenish',            'name' => 'Auto-Replenish at Check-in',   'description' => 'Automatically charge saved cards when a dog checks in with zero credits.', 'is_marketing' => true, 'sort_order' => 200],
        ];

        foreach ($features as $feature) {
            PlatformFeature::updateOrCreate(
                ['slug' => $feature['slug']],
                $feature
            );
        }
    }
}
