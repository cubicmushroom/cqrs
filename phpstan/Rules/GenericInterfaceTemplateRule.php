<?php

declare(strict_types=1);

namespace CubicMushroom\Cqrs\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * PHPStan rule to enforce that implementations/extensions of generic types must specify template parameters.
 *
 * This rule automatically detects any interface or class with @template annotations and ensures
 * that implementations/extensions have proper @implements/@extends annotations with type parameters.
 *
 * @implements Rule<InClassNode>
 */
final class GenericInterfaceTemplateRule implements Rule
{
    public function getNodeType(): string
    {
        return InClassNode::class;
    }


    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $scope->getClassReflection();

        if ($classReflection === null) {
            return [];
        }

        // Get the class node
        $classNode = $node->getOriginalNode();
        if (!$classNode instanceof Class_) {
            return [];
        }

        $errors = [];

        // Check implemented interfaces
        foreach ($classReflection->getInterfaces() as $interface) {
            if ($this->isGenericType($interface)) {
                $missingAnnotation = $this->getMissingAnnotation($classNode, $interface->getName(), 'implements');
                if ($missingAnnotation !== null) {
                    $errors[] = $this->createError($classReflection, $interface, 'implements', $missingAnnotation);
                }
            }
        }

        // Check extended parent classes
        $parentClass = $classReflection->getParentClass();
        if ($parentClass !== null && $this->isGenericType($parentClass)) {
            $missingAnnotation = $this->getMissingAnnotation($classNode, $parentClass->getName(), 'extends');
            if ($missingAnnotation !== null) {
                $errors[] = $this->createError($classReflection, $parentClass, 'extends', $missingAnnotation);
            }
        }

        return $errors;
    }


    /**
     * Check if a type (interface or class) is generic by looking for @template annotations.
     */
    private function isGenericType(ClassReflection $typeReflection): bool
    {
        $docComment = $typeReflection->getResolvedPhpDoc();
        
        if ($docComment === null) {
            return false;
        }

        // Check if the type has @template annotations
        return count($docComment->getTemplateTags()) > 0;
    }


    /**
     * Check if the class has the required generic annotation and return missing template info if not.
     *
     * @return array{annotation: string, templates: string[]}|null
     */
    private function getMissingAnnotation(Class_ $classNode, string $typeName, string $annotationType): ?array
    {
        $docComment = $classNode->getDocComment();

        if ($docComment === null) {
            return $this->getTemplateInfo($typeName, $annotationType);
        }

        $docText = $docComment->getText();
        $shortTypeName = $this->getShortTypeName($typeName);

        // Check for @implements/@extends TypeName<...> pattern
        $pattern = sprintf('/@%s\s+.*%s\s*<[^>]+>/', $annotationType, preg_quote($shortTypeName, '/'));
        
        if (!(bool)preg_match($pattern, $docText)) {
            return $this->getTemplateInfo($typeName, $annotationType);
        }

        return null;
    }


    /**
     * Get template parameter information for a generic type.
     *
     * @return array{annotation: string, templates: string[]}
     */
    private function getTemplateInfo(string $typeName, string $annotationType): array
    {
        $shortTypeName = $this->getShortTypeName($typeName);
        
        // For now, we'll use a generic template parameter name
        // In a more advanced version, we could inspect the actual @template tags
        return [
            'annotation' => $annotationType,
            'templates' => ['T'], // Generic template parameter
        ];
    }


    private function createError(
        ClassReflection $classReflection,
        ClassReflection $genericType,
        string $annotationType,
        array $templateInfo
    ): \PHPStan\Rules\RuleError {
        $shortTypeName = $this->getShortTypeName($genericType->getName());
        $templateParams = implode(', ', $templateInfo['templates']);
        
        $message = sprintf(
            'Class %s %ss %s but is missing @%s %s<%s> annotation. '
            . 'Please specify the type parameter using generics, e.g., @%s %s<UserDto|null>',
            $classReflection->getName(),
            $annotationType,
            $shortTypeName,
            $annotationType,
            $shortTypeName,
            $templateParams,
            $annotationType,
            $shortTypeName,
        );

        return RuleErrorBuilder::message($message)
            ->identifier('phpstan.genericType.missingTemplate')
            ->build();
    }


    private function getShortTypeName(string $fullyQualifiedName): string
    {
        $parts = explode('\\', $fullyQualifiedName);
        
        return end($parts);
    }
}
