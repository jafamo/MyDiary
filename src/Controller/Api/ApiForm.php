<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Envía el cuerpo JSON de una petición de la API a un FormType de la web, para que las dos entradas
 * validen con las mismas reglas. Sin symfony/validator, las reglas son las del propio formulario:
 * campos `required`, `attr.maxlength` y lo que sus transformadores admiten. Cualquier fallo es un 422
 * que nombra el campo; nada llega a la entidad si falta un obligatorio.
 */
class ApiForm
{
    public function __construct(
        private readonly FormFactoryInterface $formFactory,
    ) {
    }

    /**
     * @param class-string<FormTypeInterface<mixed>> $type
     * @param bool                                   $partial true en un PATCH: los campos ausentes conservan su valor
     *
     * @return FormInterface<mixed>
     */
    public function submit(string $type, ?object $data, Request $request, bool $partial = false): FormInterface
    {
        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload) || ([] !== $payload && array_is_list($payload))) {
            throw ApiException::badRequest('El cuerpo de la petición debe ser un objeto JSON.');
        }

        $form = $this->formFactory->create($type, $data, ['csrf_protection' => false]);

        foreach (array_keys($payload) as $field) {
            if (!$form->has((string) $field)) {
                throw ApiException::validationFailed(sprintf('El campo "%s" no se admite.', $field));
            }
        }

        foreach ($form as $name => $child) {
            if ($partial && !\array_key_exists($name, $payload)) {
                continue;
            }

            $value = $payload[$name] ?? null;
            if ($child->isRequired() && self::isBlank($value)) {
                throw ApiException::validationFailed(sprintf('El campo "%s" es obligatorio.', $name));
            }

            $maxLength = $child->getConfig()->getOption('attr')['maxlength'] ?? null;
            if (null !== $maxLength && \is_string($value) && mb_strlen(trim($value)) > $maxLength) {
                throw ApiException::validationFailed(sprintf('El campo "%s" admite como máximo %d caracteres.', $name, $maxLength));
            }
        }

        $form->submit(self::stringify($payload), !$partial);

        // Un valor no transformable deja el campo sin sincronizar; una opción desconocida en un campo
        // `multiple` lo deja sincronizado pero con un error.
        foreach ($form as $name => $child) {
            if (!$child->isSynchronized() || ($child->isSubmitted() && !$child->isValid())) {
                throw ApiException::validationFailed(sprintf('El campo "%s" no es válido.', $name));
            }
        }

        return $form;
    }

    private static function isBlank(mixed $value): bool
    {
        return null === $value || [] === $value || (\is_string($value) && '' === trim($value));
    }

    /**
     * Los formularios trabajan con cadenas, como en un envío HTTP; en JSON, los ids y otros números
     * llegan como enteros.
     */
    private static function stringify(mixed $value): mixed
    {
        return match (true) {
            \is_array($value) => array_map(self::stringify(...), $value),
            \is_int($value), \is_float($value) => (string) $value,
            default => $value,
        };
    }
}
