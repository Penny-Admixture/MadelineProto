<?php

namespace danog\MadelineProto\PhpDoc;

use danog\MadelineProto\Logger;
use danog\MadelineProto\PhpDocBuilder;
use phpDocumentor\Reflection\DocBlock\Tags\InvalidTag;
use phpDocumentor\Reflection\DocBlock\Tags\Property;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionMethod;

class ClassDoc extends GenericDoc
{
    /**
     * Properties.
     *
     * @var array<string, PropertyDoc>
     */
    private array $properties = [];
    /**
     * Methods.
     *
     * @var array<string, MethodDoc>
     */
    private array $methods = [];
    public function __construct(PhpDocBuilder $builder, ReflectionClass $reflectionClass)
    {
        $this->builder = $builder;
        $this->name = $reflectionClass->getName();
        $doc = $reflectionClass->getDocComment();
        if (!$doc) {
            Logger::log($reflectionClass->getName()." has no PHPDOC");
            $this->ignore = true;
            return;
        }
        $doc = $this->builder->getFactory()->create($doc);

        parent::__construct($doc, $reflectionClass);

        $tags = $doc->getTags();
        foreach ($tags as $tag) {
            if ($tag instanceof Property) {
                $this->properties[$tag->getVariableName()] = new PropertyDoc(
                    $this->builder,
                    $tag->getName(),
                    $tag->getType(),
                    $tag->getDescription()
                );
            }
            if ($tag instanceof InvalidTag && $tag->getName() === 'property') {
                [$type, $description] = \explode(" $", $tag->render(), 2);
                $description .= ' ';
                [$varName, $description] = \explode(" ", $description, 2);
                $type = \str_replace('@property ', '', $type);
                $description ??= '';
                $this->properties[$varName] = new PropertyDoc(
                    $this->builder,
                    $varName,
                    $type,
                    $description
                );
            }
        }
        $constants = [];
        foreach ($reflectionClass->getConstants() as $key => $value) {
            $refl = new ReflectionClassConstant($reflectionClass->getName(), $key);
            if (!$refl->isPublic()) {
                continue;
            }
            $description = '';
            if ($refl->getDocComment()) {
                $docConst = $this->builder->getFactory()->create($refl->getDocComment());
                if ($this->builder->shouldIgnore($refl->getDeclaringClass()->getName())) {
                    continue;
                }
                $description .= $docConst->getSummary();
                if ($docConst->getDescription()) {
                    $description .= "\n\n";
                    $description .= $docConst->getDescription();
                }
                if ($docConst->getTagsByName('internal')) {
                    continue;
                }
            }
            $description = \trim($description);
            $constants[$key] = [
                $value,
                $description
            ];
        }


        foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (str_starts_with($method->getName(), '__') && $method !== '__construct') {
                continue;
            }
            $this->methods[$method->getName()] = new MethodDoc($this->builder, $method);
        }

        $this->methods = \array_filter($this->methods, fn (MethodDoc $doc): bool => !$doc->shouldIgnore());
        //$this->properties = \array_filter($this->properties, fn (PropertyDoc $doc): bool => !$doc->shouldIgnore());
    }

    public function format(): string
    {
        $init = parent::format();
        $methods = '';
        $properties = '';
        if ($this->methods) {
            $init .= "\n";
            foreach ($this->methods as $method) {
                $init .= "* ".$method->getSignature()."\n";
            }
            $init .= "\n";
            $init .= "## Methods:\n$methods\n";
            foreach ($this->methods as $method) {
                $init .= $method->format();
                $init .= "\n";
            }
        }
        if ($properties) {
            $init .= "## Properties:\n$properties\n";
            /*foreach ($this->properties as $property) {
                $init .= $property->format();
                $init .= "\n";
            }*/
        }
        return $init;
    }
}
