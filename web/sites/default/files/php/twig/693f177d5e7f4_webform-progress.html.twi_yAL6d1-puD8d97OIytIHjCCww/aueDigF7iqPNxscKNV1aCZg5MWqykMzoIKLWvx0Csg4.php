<?php

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Extension\CoreExtension;
use Twig\Extension\SandboxExtension;
use Twig\Markup;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Source;
use Twig\Template;
use Twig\TemplateWrapper;

/* modules/contrib/webform/templates/webform-progress.html.twig */
class __TwigTemplate_4c70091e059948d7bcb17369c6bbaa52 extends Template
{
    private Source $source;
    /**
     * @var array<string, Template>
     */
    private array $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
        ];
        $this->sandbox = $this->extensions[SandboxExtension::class];
        $this->checkSecurity();
    }

    protected function doDisplay(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        // line 21
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->extensions['Drupal\Core\Template\TwigExtension']->attachLibrary("webform/webform.progress"), "html", null, true);
        yield "

<div class=\"webform-progress\">

  ";
        // line 25
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["bar"] ?? null), "html", null, true);
        yield "

  ";
        // line 27
        if ((($context["summary"] ?? null) || ($context["percentage"] ?? null))) {
            // line 28
            yield "    <div class=\"webform-progress__status\">
      ";
            // line 29
            if ((($tmp = ($context["summary"] ?? null)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
                // line 30
                yield "        <span class=\"webform-progress__summary\" data-webform-progress-summary>";
                yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["summary"] ?? null), "html", null, true);
                yield "</span>
        ";
                // line 31
                if ((($tmp = ($context["percentage"] ?? null)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
                    // line 32
                    yield "          <span class=\"webform-progress__percentage\">(<span data-webform-progress-percentage>";
                    yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["percentage"] ?? null), "html", null, true);
                    yield "</span>)</span>
        ";
                }
                // line 34
                yield "      ";
            } else {
                // line 35
                yield "        <span class=\"webform-progress__percentage\" data-webform-progress-percentage>";
                yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["percentage"] ?? null), "html", null, true);
                yield "</span>
      ";
            }
            // line 37
            yield "    </div>
  ";
        }
        // line 39
        yield "
</div>
";
        $this->env->getExtension('\Drupal\Core\Template\TwigExtension')
            ->checkDeprecations($context, ["bar", "summary", "percentage"]);        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "modules/contrib/webform/templates/webform-progress.html.twig";
    }

    /**
     * @codeCoverageIgnore
     */
    public function isTraitable(): bool
    {
        return false;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDebugInfo(): array
    {
        return array (  89 => 39,  85 => 37,  79 => 35,  76 => 34,  70 => 32,  68 => 31,  63 => 30,  61 => 29,  58 => 28,  56 => 27,  51 => 25,  44 => 21,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "modules/contrib/webform/templates/webform-progress.html.twig", "/app/web/modules/contrib/webform/templates/webform-progress.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = ["if" => 27];
        static $filters = ["escape" => 21];
        static $functions = ["attach_library" => 21];

        try {
            $this->sandbox->checkSecurity(
                ['if'],
                ['escape'],
                ['attach_library'],
                $this->source
            );
        } catch (SecurityError $e) {
            $e->setSourceContext($this->source);

            if ($e instanceof SecurityNotAllowedTagError && isset($tags[$e->getTagName()])) {
                $e->setTemplateLine($tags[$e->getTagName()]);
            } elseif ($e instanceof SecurityNotAllowedFilterError && isset($filters[$e->getFilterName()])) {
                $e->setTemplateLine($filters[$e->getFilterName()]);
            } elseif ($e instanceof SecurityNotAllowedFunctionError && isset($functions[$e->getFunctionName()])) {
                $e->setTemplateLine($functions[$e->getFunctionName()]);
            }

            throw $e;
        }

    }
}
