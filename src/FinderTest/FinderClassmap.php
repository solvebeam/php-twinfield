<?php

namespace SolveBeam\Twinfield\Finder;

use SolveBeam\Twinfield\Finder\Type;
use Soap\Encoding\ClassMap\ClassMapCollection;
use Soap\Encoding\ClassMap\ClassMap;

class FinderClassmap
{
    public static function types(): \Soap\Encoding\ClassMap\ClassMapCollection
    {
        return new ClassMapCollection(
            new ClassMap('http://www.twinfield.com/', 'ArrayOfArrayOfString', Type\ArrayOfArrayOfString::class),
            new ClassMap('http://www.twinfield.com/', 'ArrayOfString', Type\ArrayOfString::class),
            new ClassMap('http://www.twinfield.com/', 'ArrayOfMessageOfErrorCodes', Type\ArrayOfMessageOfErrorCodes::class),
            new ClassMap('http://www.twinfield.com/', 'MessageOfErrorCodes', Type\MessageOfErrorCodes::class),
            new ClassMap('http://www.twinfield.com/', 'FinderData', Type\FinderData::class),
            new ClassMap('http://www.twinfield.com/', 'Header', Type\Header::class),
            new ClassMap('http://www.twinfield.com/', 'Search', Type\Search::class),
            new ClassMap('http://www.twinfield.com/', 'SearchResponse', Type\SearchResponse::class),
        );
    }

    public static function enums(): \Soap\Encoding\ClassMap\ClassMapCollection
    {
        return new ClassMapCollection(
            new ClassMap('http://www.twinfield.com/', 'MessageType', Type\MessageType::class),
            new ClassMap('http://www.twinfield.com/', 'ErrorCodes', Type\ErrorCodes::class),
        );
    }
}

