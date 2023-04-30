<?php

namespace PixelTrack\DataTransfers\Generator;

use PixelTrack\DataTransfers\DefinitionBuilder;
use PixelTrack\DataTransfers\OutputBuilder;

class Generator
{
    public function __construct(
        private readonly DefinitionBuilder $definitionBuilder,
        private readonly OutputBuilder $outputBuilder
    ) {
    }

    public function generate(): bool
    {
        $template = $this->definitionBuilder->getTwigEnvironment()->load('class.twig');

        $classContent = $template->render(
            [
                'className' => 'TrackTransfer',
                'abstractClass' => 'AbstractTransfer',
                'properties' => [
                    [
                        'visibility' => 'private',
                        'type' => 'int',
                        'camelCaseName' => 'id',
                        'getPrefix' => 'get',
                    ],
                    [
                        'visibility' => 'private',
                        'type' => 'int',
                        'camelCaseName' => 'userId',
                        'getPrefix' => 'get',
                    ],
                    [
                        'visibility' => 'private',
                        'type' => 'string',
                        'camelCaseName' => 'name',
                        'getPrefix' => 'get',
                    ],
                    [
                        'visibility' => 'private',
                        'type' => 'string',
                        'camelCaseName' => 'key',
                        'getPrefix' => 'get',
                    ],
                    [
                        'visibility' => 'private',
                        'type' => 'string',
                        'camelCaseName' => 'filename',
                        'getPrefix' => 'get',
                    ]
                ],
            ]
        );

        $this->outputBuilder->save($classContent, 'TrackTransfer');

        $classContent = $template->render(
            [
                'className' => 'UserTransfer',
                'abstractClass' => 'AbstractTransfer',
                'properties' => [
                    [
                        'visibility' => 'private',
                        'type' => 'int',
                        'camelCaseName' => 'id',
                        'getPrefix' => 'get',
                    ],
                    [
                        'visibility' => 'private',
                        'type' => 'string',
                        'camelCaseName' => 'key',
                        'getPrefix' => 'get',
                    ],
                    [
                        'visibility' => 'private',
                        'type' => 'string',
                        'camelCaseName' => 'email',
                        'getPrefix' => 'get',
                    ]
                ],
            ]
        );

        $this->outputBuilder->save($classContent, 'UserTransfer');

        return true;
    }
}
