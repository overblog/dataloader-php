<?php

/*
 * This file is part of the DataLoaderPhp package.
 *
 * (c) Overblog <http://github.com/overblog/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Overblog\DataLoader\Test\Functional\Webonyx\GraphQL;

use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Overblog\DataLoader\DataLoader;

class Schema
{
    public static function build(DataLoader $dataLoader)
    {
        $characterType = null;

        /**
         * This implements the following type system shorthand:
         *   type Character : Character {
         *     id: String!
         *     name: String
         *     friends: [Character]
         *   }
         */
        $characterType = new ObjectType([
            'name' => 'Character',
            'fields' => function () use (&$characterType, $dataLoader) {
                return [
                    'id' => [
                        'type' => new NonNull(Type::string()),
                        'description' => 'The id of the character.',
                    ],
                    'name' => [
                        'type' => Type::string(),
                        'description' => 'The name of the character.',
                    ],
                    'friends' => [
                        'type' => Type::listOf($characterType),
                        'description' => 'The friends of the character, or an empty list if they have none.',
                        'resolve' => function ($character) use ($dataLoader) {
                            $promise = $dataLoader->loadMany($character['friends']);
                            return $promise;
                        },
                    ],
                ];
            },
        ]);

        /**
         * This implements the following type system shorthand:
         *   type Query {
         *     character(id: String!): Character
         *   }
         *
         */
        $queryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'character' => [
                    'type' => $characterType,
                    'args' => [
                        'id' => [
                            'name' => 'id',
                            'description' => 'id of the character',
                            'type' => Type::nonNull(Type::string())
                        ]
                    ],
                    'resolve' => function ($root, $args) use ($dataLoader) {
                        $promise = $dataLoader->load($args['id']);
                        return $promise;
                    },
                ],
            ]
        ]);

        return new \GraphQL\Schema(['query' => $queryType]);
    }
}
