<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BreedSeeder extends Seeder
{
    private static array $breeds = [
        // Sporting
        'Brittany',
        'Chesapeake Bay Retriever',
        'Cocker Spaniel',
        'English Setter',
        'English Springer Spaniel',
        'German Shorthaired Pointer',
        'Golden Retriever',
        'Irish Setter',
        'Irish Water Spaniel',
        'Labrador Retriever',
        'Pointer',
        'Portuguese Water Dog',
        'Vizsla',
        'Weimaraner',

        // Hound
        'Afghan Hound',
        'Basenji',
        'Basset Hound',
        'Beagle',
        'Bloodhound',
        'Borzoi',
        'Dachshund',
        'Greyhound',
        'Italian Greyhound',
        'Norwegian Elkhound',
        'Rhodesian Ridgeback',
        'Whippet',

        // Working
        'Akita',
        'Alaskan Malamute',
        'Bernese Mountain Dog',
        'Boxer',
        'Doberman',
        'Doberman Pinscher',
        'Giant Schnauzer',
        'Great Dane',
        'Great Pyrenees',
        'Mastiff',
        'Newfoundland',
        'Rottweiler',
        'Saint Bernard',
        'Samoyed',
        'Siberian Husky',
        'Husky',

        // Terrier
        'Airedale Terrier',
        'Bull Terrier',
        'Cairn Terrier',
        'Jack Russell Terrier',
        'Miniature Schnauzer',
        'Norfolk Terrier',
        'Scottish Terrier',
        'Soft Coated Wheaten Terrier',
        'Staffordshire Bull Terrier',
        'Welsh Terrier',
        'West Highland White Terrier',
        'Wire Fox Terrier',
        'Yorkshire Terrier',

        // Toy
        'Cavalier King Charles Spaniel',
        'Chihuahua',
        'Chinese Crested',
        'Maltese',
        'Papillon',
        'Pekingese',
        'Pomeranian',
        'Pug',
        'Shih Tzu',
        'Toy Poodle',

        // Non-Sporting
        'Bichon Frise',
        'Boston Terrier',
        'Bulldog',
        'Chow Chow',
        'Dalmatian',
        'French Bulldog',
        'Keeshond',
        'Lhasa Apso',
        'Miniature Poodle',
        'Poodle',
        'Shar-Pei',

        // Herding
        'Australian Shepherd',
        'Belgian Malinois',
        'Border Collie',
        'Collie',
        'Corgi',
        'German Shepherd',
        'Old English Sheepdog',
        'Pembroke Welsh Corgi',
        'Shetland Sheepdog',

        // Mixed / Other
        'Chocolate Labrador',
        'Dachshund Mix',
        'Goldendoodle',
        'Labrador Mix',
        'Labradoodle',
        'Mixed Breed',
        'Pit Bull Terrier',
    ];

    public function run(): void
    {
        $now = now();
        $rows = array_map(fn (string $name) => [
            'name' => $name,
            'created_at' => $now,
            'updated_at' => $now,
        ], self::$breeds);

        DB::table('breeds')->upsert($rows, ['name'], ['updated_at']);
    }
}
