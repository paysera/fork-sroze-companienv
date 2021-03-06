<?php

namespace Companienv\DotEnv;

class Block
{
    private $title;
    private $description;

    /** @var Variable[] */
    private $variables;

    /** @var Attribute[] */
    private $attributes;

    public function __construct(string $title = '', string $description = '', array $variables = [], array $attributes = [])
    {
        $this->title = $title;
        $this->description = $description;
        $this->variables = $variables;
        $this->attributes = $attributes;
    }

    public function appendToDescription(string $string)
    {
        $this->description .= ($this->description ? ' ' : '') . $string;
    }

    public function addVariable(Variable $variable)
    {
        $this->variables[] = $variable;
    }

    public function addAttribute(Attribute $attribute)
    {
        $this->attributes[] = $attribute;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return Attribute[]
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @return Variable[]
     */
    public function getVariables(): array
    {
        return $this->variables;
    }

    /**
     * Return only the variables that are in the block.
     *
     * @param Variable[] $variables
     *
     * @return Variable[]
     */
    public function getVariablesInBlock(array $variables)
    {
        $blockVariableNames = array_map(function (Variable $variable) {
            return $variable->getName();
        }, $this->variables);

        return array_filter($variables, function (Variable $variable) use ($blockVariableNames) {
            return in_array($variable->getName(), $blockVariableNames);
        });
    }

    /**
     * @param string $name
     *
     * @return Variable|null
     */
    public function getVariable(string $name)
    {
        foreach ($this->variables as $variable) {
            if ($variable->getName() == $name) {
                return $variable;
            }
        }

        return null;
    }

    /**
     * @param string $name
     * @param Variable|null $forVariable Will return only attribute for the given variables
     *
     * @return Attribute|null
     */
    public function getAttribute(string $name, Variable $forVariable = null)
    {
        foreach ($this->attributes as $attribute) {
            if (
                $attribute->getName() == $name
                && (
                    $forVariable === null
                    || in_array($forVariable->getName(), $attribute->getVariableNames())
                )
            ) {
                return $attribute;
            }
        }

        return null;
    }
}
