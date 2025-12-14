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

/* modules/contrib/webform/templates/webform-progress-tracker.html.twig */
class __TwigTemplate_ad63701e5a4142083c3d9051709b37a5 extends Template
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
        // line 20
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->extensions['Drupal\Core\Template\TwigExtension']->attachLibrary("webform/webform.progress.tracker"), "html", null, true);
        yield "

<ul class=\"webform-progress-tracker progress-tracker progress-tracker--center\" data-webform-progress-steps>
  ";
        // line 23
        $context['_parent'] = $context;
        $context['_seq'] = CoreExtension::ensureTraversable(($context["progress"] ?? null));
        foreach ($context['_seq'] as $context["index"] => $context["page"]) {
            // line 24
            yield "    ";
            $context["is_completed"] = ($context["index"] < ($context["current_index"] ?? null));
            // line 25
            yield "    ";
            $context["is_active"] = ($context["index"] == ($context["current_index"] ?? null));
            // line 26
            yield "    ";
            // line 27
            $context["classes"] = ["progress-step", (((($tmp =             // line 29
($context["is_completed"] ?? null)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) ? ("is-complete") : ("")), (((($tmp =             // line 30
($context["is_active"] ?? null)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) ? ("is-active") : (""))];
            // line 33
            yield "    ";
            // line 34
            $context["attributes"] = CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, $this->extensions['Drupal\Core\Template\TwigExtension']->createAttribute(), "setAttribute", [("data-webform-" . CoreExtension::getAttribute($this->env, $this->source,             // line 35
$context["page"], "type", [], "any", false, false, true, 35)), CoreExtension::getAttribute($this->env, $this->source, $context["page"], "name", [], "any", false, false, true, 35)], "method", false, false, true, 34), "setAttribute", ["title", CoreExtension::getAttribute($this->env, $this->source,             // line 36
$context["page"], "title", [], "any", false, false, true, 36)], "method", false, false, true, 35), "setAttribute", ["class", ""], "method", false, false, true, 36), "addClass", [            // line 38
($context["classes"] ?? null)], "method", false, false, true, 37);
            // line 40
            yield "    ";
            if ((($tmp = ($context["is_active"] ?? null)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
                // line 41
                yield "      ";
                $context["attributes"] = CoreExtension::getAttribute($this->env, $this->source, ($context["attributes"] ?? null), "setAttribute", ["aria-current", "step"], "method", false, false, true, 41);
                // line 42
                yield "    ";
            }
            // line 43
            yield "    <li";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["attributes"] ?? null), "html", null, true);
            yield ">
      <div class=\"progress-marker\" data-webform-progress-step data-webform-progress-link data-text=\"";
            // line 44
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["index"] + 1), "html", null, true);
            yield "\"></div>
      ";
            // line 45
            if ((Twig\Extension\CoreExtension::length($this->env->getCharset(), ($context["progress"] ?? null)) < ($context["max_pages"] ?? null))) {
                // line 46
                yield "        <div class=\"progress-text\">
          <div class=\"progress-title\" data-webform-progress-link>
            <span class=\"visually-hidden\" data-webform-progress-state>";
                // line 48
                if ((($context["is_active"] ?? null) || ($context["is_completed"] ?? null))) {
                    yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar((((($tmp = ($context["is_active"] ?? null)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) ? (t("Current")) : (t("Completed"))));
                }
                yield "</span>
            ";
                // line 49
                yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, $context["page"], "title", [], "any", false, false, true, 49), "html", null, true);
                yield "
          </div>
        </div>
      ";
            }
            // line 53
            yield "    </li>
  ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['index'], $context['page'], $context['_parent']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 55
        yield "</ul>
";
        $this->env->getExtension('\Drupal\Core\Template\TwigExtension')
            ->checkDeprecations($context, ["progress", "current_index", "max_pages"]);        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "modules/contrib/webform/templates/webform-progress-tracker.html.twig";
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
        return array (  117 => 55,  110 => 53,  103 => 49,  97 => 48,  93 => 46,  91 => 45,  87 => 44,  82 => 43,  79 => 42,  76 => 41,  73 => 40,  71 => 38,  70 => 36,  69 => 35,  68 => 34,  66 => 33,  64 => 30,  63 => 29,  62 => 27,  60 => 26,  57 => 25,  54 => 24,  50 => 23,  44 => 20,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "modules/contrib/webform/templates/webform-progress-tracker.html.twig", "/app/web/modules/contrib/webform/templates/webform-progress-tracker.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = ["for" => 23, "set" => 24, "if" => 40];
        static $filters = ["escape" => 20, "length" => 45, "t" => 48];
        static $functions = ["attach_library" => 20, "create_attribute" => 34];

        try {
            $this->sandbox->checkSecurity(
                ['for', 'set', 'if'],
                ['escape', 'length', 't'],
                ['attach_library', 'create_attribute'],
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
