<?php
namespace Wwwision\Neos\Form\Fusion;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Response;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Form\Core\Model\FormDefinition;
use Neos\Form\Core\Model\Renderable\RenderableInterface;
use Neos\Form\Exception\PresetNotFoundException;
use Neos\Fusion\FusionObjects\AbstractFusionObject;
use Neos\Utility\Arrays;

class FormImplementation extends AbstractFusionObject
{

    /**
     * @Flow\InjectConfiguration(package="Neos.Form")
     * @var array
     */
    protected $formSettings;

    public function getPath(): string
    {
        return $this->path;
    }

    public function evaluate()
    {
        try {
            $formDefinition = $this->buildFormDefinition();
        } catch (PresetNotFoundException $exception) {
            return $exception->getMessage();
        }
        $controllerContext = $this->runtime->getControllerContext();

        $response = new Response($controllerContext->getResponse());
        $actionRequest = $controllerContext->getRequest();
        if (!$actionRequest instanceof ActionRequest) {
            throw new \RuntimeException(sprintf('The Form ControllerContext requires an instance of ActionRequest, instance of "%s" given', get_class($controllerContext->getRequest())), 1499345873);
        }
        $formRuntime = $formDefinition->bind($actionRequest, $response);

        $this->runtime->pushContext('formRuntime', $formRuntime);
        $this->runtime->evaluate($this->path . '/renderCallbacks');
        $this->runtime->popContext();

        return $formRuntime->render();
    }

    protected function buildFormDefinition(): ?FormDefinition
    {
        $presetName = $this->getPresetName();
        $formDefaults = $this->getPresetConfiguration($presetName);

        $form = new FormDefinition($this->getIdentifier(), $formDefaults, $this->getFormElementType());

        $this->runtime->pushContext('form', $form);
        $this->runtime->evaluate($this->path . '/firstPage');
        $this->runtime->evaluate($this->path . '/furtherPages');
        $this->runtime->evaluate($this->path . '/finishers');
        $this->runtime->popContext();

        /** @var RenderableInterface $renderable */
        foreach ($form->getRenderablesRecursively() as $renderable) {
            $renderable->onBuildingFinished();
        }

        return $form;
    }

    private function getPresetConfiguration(string $presetName): array
    {
        if (!isset($this->formSettings['presets'][$presetName])) {
            throw new PresetNotFoundException(sprintf('The Preset "%s" was not found underneath Neos: Form: presets.', $presetName), 1499240977);
        }
        $preset = $this->formSettings['presets'][$presetName];
        if (isset($preset['parentPreset'])) {
            $parentPreset = $this->getPresetConfiguration($preset['parentPreset']);
            unset($preset['parentPreset']);
            $preset = Arrays::arrayMergeRecursiveOverrule($parentPreset, $preset);
        }
        return $preset;
    }

    private function getPresetName(): string
    {
        return $this->fusionValue('presetName');
    }

    private function getIdentifier(): string
    {
        return $this->fusionValue('identifier');
    }

    private function getFormElementType(): string
    {
        return $this->fusionValue('formElementType');
    }
}