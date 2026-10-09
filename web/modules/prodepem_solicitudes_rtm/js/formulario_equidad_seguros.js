/**
 * @file
 * Mobile swipe navigation, table validation, and styling enhancements
 * for formulario_equidad_seguros using pure Vanilla JavaScript.
 */

(function () {
  'use strict';

  /**
   * Determina si una tabla corresponde a la tabla de beneficiarios.
   */
  function isBeneficiariosTable(table) {
    if (!table) return false;
    const form = table.closest('.webform-submission-formulario-equidad-seguros-add-form, .formulario-equidad-seguros, form[id*="formulario-equidad-seguros"]');
    if (!form) return false;

    // Buscar columna de porcentaje en encabezados
    const headers = table.querySelectorAll('th');
    for (let i = 0; i < headers.length; i++) {
      const txt = (headers[i].textContent || '').toLowerCase().trim();
      if (txt === '%' || txt.includes('porcent') || txt.includes('participa')) {
        return true;
      }
    }

    // Buscar en identificadores o clases del contenedor
    const wrapper = table.closest('.webform-multiple-table-wrapper, .form-item, fieldset') || table;
    const info = (wrapper.getAttribute('data-drupal-selector') || '') + ' ' + (wrapper.id || '') + ' ' + (wrapper.className || '');
    if (info.toLowerCase().includes('beneficiar')) {
      return true;
    }

    return false;
  }

  /**
   * Obtiene los inputs de porcentaje de una tabla de beneficiarios.
   */
  function getPercentageInputs(table) {
    // 1. Buscar por índice de columna de encabezado '%'
    let percentColIndex = -1;
    const headers = table.querySelectorAll('thead th, tr:first-child th');
    for (let i = 0; i < headers.length; i++) {
      const txt = (headers[i].textContent || '').toLowerCase().trim();
      if (txt === '%' || txt.includes('porcent') || txt.includes('participa')) {
        percentColIndex = i;
        break;
      }
    }

    if (percentColIndex !== -1) {
      const rows = table.querySelectorAll('tbody tr');
      const inputs = [];
      rows.forEach(function (row) {
        if (row.children[percentColIndex]) {
          const inp = row.children[percentColIndex].querySelector('input[type="number"], input[type="text"], input:not([type="hidden"])');
          if (inp) {
            inputs.push(inp);
          }
        }
      });
      if (inputs.length > 0) {
        return inputs;
      }
    }

    // 2. Buscar por nombre de campo
    const byName = table.querySelectorAll('input[name*="porcent"], input[name*="porcentaje"], input[data-drupal-selector*="porcent"]');
    if (byName.length > 0) {
      return Array.from(byName);
    }

    return [];
  }

  /**
   * Calcula el total de los porcentajes y detecta si hay filas diligenciadas.
   */
  function calculateBeneficiariosTotal(table) {
    const inputs = getPercentageInputs(table);
    let sum = 0;
    let hasAnyValue = false;

    inputs.forEach(function (inp) {
      const raw = (inp.value || '').trim();
      if (raw !== '') {
        hasAnyValue = true;
        const val = parseFloat(raw.replace(',', '.'));
        if (!isNaN(val)) {
          sum += val;
        }
      }
    });

    // Detectar si alguna fila tiene campos diligenciados
    const rows = table.querySelectorAll('tbody tr');
    let hasAnyRowData = false;
    rows.forEach(function (row) {
      const allInputs = row.querySelectorAll('input:not([type="hidden"]), select, textarea');
      allInputs.forEach(function (el) {
        if (el.value && el.value.trim() !== '') {
          hasAnyRowData = true;
        }
      });
    });

    return {
      sum: Math.round(sum * 100) / 100,
      inputs: inputs,
      hasAnyValue: hasAnyValue,
      hasAnyRowData: hasAnyRowData
    };
  }

  /**
   * Obtiene o crea el elemento de feedback visual debajo de la tabla.
   */
  function getOrCreateFeedbackEl(table) {
    let feedbackEl = table.parentNode.querySelector('.beneficiarios-porcentaje-feedback');
    if (!feedbackEl) {
      feedbackEl = document.createElement('div');
      feedbackEl.className = 'beneficiarios-porcentaje-feedback';
      feedbackEl.style.display = 'none';
      table.parentNode.insertBefore(feedbackEl, table.nextSibling);
    }
    return feedbackEl;
  }

  /**
   * Valida la tabla de beneficiarios y actualiza el mensaje de feedback.
   */
  function validateBeneficiariosTable(table, showValidation) {
    if (!isBeneficiariosTable(table)) return true;

    const res = calculateBeneficiariosTotal(table);
    const feedbackEl = getOrCreateFeedbackEl(table);

    // Si la tabla no tiene ninguna fila diligenciada ni porcentaje escrito
    if (!res.hasAnyValue && !res.hasAnyRowData) {
      feedbackEl.style.display = 'none';
      table.classList.remove('beneficiarios-table--error');
      res.inputs.forEach(function (inp) {
        inp.classList.remove('beneficiarios-porcentaje-input--error');
        inp.classList.remove('beneficiarios-porcentaje-input--valid');
      });
      return true;
    }

    if (res.sum === 100) {
      // Válido: Total es exactamente 100%
      feedbackEl.className = 'beneficiarios-porcentaje-feedback beneficiarios-porcentaje-feedback--valid';
      feedbackEl.innerHTML = '<span class="feedback-icon">✓</span> Total porcentaje asignado: <strong>100%</strong>';
      feedbackEl.style.display = 'flex';
      table.classList.remove('beneficiarios-table--error');
      res.inputs.forEach(function (inp) {
        inp.classList.remove('beneficiarios-porcentaje-input--error');
        if (inp.value && inp.value.trim() !== '') {
          inp.classList.add('beneficiarios-porcentaje-input--valid');
        }
      });
      return true;
    } else {
      // Inválido: La suma es diferente a 100%
      if (showValidation) {
        feedbackEl.className = 'beneficiarios-porcentaje-feedback beneficiarios-porcentaje-feedback--error';
        feedbackEl.innerHTML = '<span class="feedback-icon">⚠</span> El porcentaje total de los beneficiarios debe ser igual a <strong>100%</strong>. (Total actual: <strong>' + res.sum + '%</strong>)';
        feedbackEl.style.display = 'flex';
        table.classList.add('beneficiarios-table--error');
        res.inputs.forEach(function (inp) {
          inp.classList.remove('beneficiarios-porcentaje-input--valid');
          inp.classList.add('beneficiarios-porcentaje-input--error');
        });
      }
      return false;
    }
  }

  // =========================================================================
  // Delegación de eventos en puro Vanilla JS (funciona siempre, incluso con AJAX)
  // =========================================================================

  // 1. Pérdida de foco en la tabla de beneficiarios (focusout)
  document.addEventListener('focusout', function (e) {
    const table = e.target.closest('table');
    if (!table || !isBeneficiariosTable(table)) {
      return;
    }

    setTimeout(function () {
      const activeEl = document.activeElement;
      // Si el nuevo elemento con foco sigue dentro de la misma tabla, no validar todavía
      if (activeEl && table.contains(activeEl)) {
        return;
      }
      // La tabla perdió el foco: validar y mostrar mensaje
      validateBeneficiariosTable(table, true);
    }, 70);
  });

  // 2. Actualización en tiempo real al escribir en la tabla si ya se mostró feedback
  document.addEventListener('input', function (e) {
    const table = e.target.closest('table');
    if (!table || !isBeneficiariosTable(table)) {
      return;
    }
    const feedbackEl = table.parentNode.querySelector('.beneficiarios-porcentaje-feedback');
    if (feedbackEl && feedbackEl.style.display !== 'none') {
      validateBeneficiariosTable(table, true);
    }
  });

  // 3. Manejo de botones de navegación (Siguiente) y Envío final
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('button, input[type="submit"], input[type="button"]');
    if (!btn) return;

    const form = btn.closest('.webform-submission-formulario-equidad-seguros-add-form, .formulario-equidad-seguros');
    if (!form) return;

    const table = form.querySelector('.webform-multiple-table, table');
    if (!table || !isBeneficiariosTable(table)) return;

    const name = (btn.name || '').toLowerCase();
    const drupalSelector = (btn.getAttribute('data-drupal-selector') || '').toLowerCase();
    const cls = btn.className || '';

    const isNextButton = cls.includes('webform-button--next') || name.includes('wizard_next') || drupalSelector.includes('wizard-next');
    const isPrevButton = cls.includes('webform-button--previous') || name.includes('wizard_prev') || drupalSelector.includes('wizard-prev');
    const isFinalSubmit = (cls.includes('webform-button--submit') || name === 'op' || drupalSelector.includes('submit') || btn.type === 'submit') && !isNextButton && !isPrevButton;

    if (isNextButton) {
      // En el Paso 2 (tabla visible): actualizar alerta visual si hay error pero PERMITIR navegar en el wizard
      if (table.offsetParent !== null) {
        const res = calculateBeneficiariosTotal(table);
        if (res.hasAnyValue || res.hasAnyRowData) {
          if (res.sum !== 100) {
            validateBeneficiariosTable(table, true);
            // NO bloqueamos para permitir navegación libre entre pasos
          }
        }
      }
      return;
    }

    if (isFinalSubmit) {
      // Al final del formulario: si el error persiste, bloquear el envío
      const res = calculateBeneficiariosTotal(table);
      if ((res.hasAnyValue || res.hasAnyRowData) && res.sum !== 100) {
        e.preventDefault();
        e.stopPropagation();
        validateBeneficiariosTable(table, true);
        alert('Atención: El porcentaje total de los beneficiarios debe ser igual a 100% (la suma actual es ' + res.sum + '%). Por favor revise el Paso 2 de beneficiarios antes de enviar la solicitud.');
      }
    }
  }, true);

  /**
   * Inicialización de mejoras estéticas y gestos en Vanilla JS.
   */
  function initVanillaEnhancements(root) {
    const context = root || document;
    const forms = context.querySelectorAll ? context.querySelectorAll('.webform-submission-formulario-equidad-seguros-add-form, .formulario-equidad-seguros') : [];

    forms.forEach(function (form) {
      // 1. Datos automáticos de ASPU cuando selector_tomador es 2
      const selectorTomadorInput = form.querySelector('[name="selector_tomador"]');
      const selectorVal = selectorTomadorInput ? (selectorTomadorInput.value || '2') : '2';

      if (selectorVal === '2') {
        const aspuFields = {
          'tomador': 'Asociación Sindical de Profesores Universitarios - ASPU',
          'c_c_nit': '830001998',
          'nit_tomador': '830001998',
          'direccion': 'Cra 6 # 77-305',
          'direccion_tomador': 'Cra 6 # 77-305',
          'ciudad': 'Montería',
          'ciudad_tomador': 'Montería',
          'telefono': '3242560489',
          'telefono_tomador': '3242560489'
        };

        Object.keys(aspuFields).forEach(function (fieldName) {
          const field = form.querySelector('[name="' + fieldName + '"]');
          if (field && (!field.value || field.value.trim() === '')) {
            field.value = aspuFields[fieldName];
          }
        });
      }

      // 2. Homologar estilo de "¿Está interesado en otros productos?"
      const titles = form.querySelectorAll('label, legend, .fieldset-legend, .form-item__label, h2, h3, h4');
      titles.forEach(function (el) {
        const text = (el.textContent || '').trim();
        if (text.toLowerCase().includes('interesado en otros productos')) {
          el.classList.add('estilo-seguros-adicionales-title');
          const formItem = el.closest('.form-item, fieldset');
          if (formItem) {
            formItem.classList.add('wrapper-estilo-seguros-adicionales');
          }
        }
      });

      // 3. Alineación de checkboxes
      const checkboxes = form.querySelectorAll('input[type="checkbox"], input[type="radio"]');
      checkboxes.forEach(function (cb) {
        const parent = cb.closest('.form-type-checkbox, .form-type-radio, .form-type-boolean, .form-item');
        if (parent) {
          parent.classList.add('checkbox-aligned-row');
        }
        const label = parent ? parent.querySelector('label') : cb.nextElementSibling;
        if (label && label.tagName === 'LABEL') {
          label.classList.add('checkbox-aligned-label');
        }
      });

      // 4. Ancho para columna "Tipo de documento"
      const tables = form.querySelectorAll('.webform-multiple-table, table');
      tables.forEach(function (table) {
        const headers = table.querySelectorAll('th');
        headers.forEach(function (th, colIndex) {
          const headerText = (th.textContent || '').toLowerCase().trim();
          if (headerText.includes('tipo') && (headerText.includes('documento') || headerText.includes('doc'))) {
            th.classList.add('col-tipo-documento');
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(function (row) {
              const cell = row.children[colIndex];
              if (cell) {
                cell.classList.add('col-tipo-documento');
              }
            });
          }
        });
      });

      // 5. Swipe Navigation
      if (!form.dataset.swipeInitialized) {
        form.dataset.swipeInitialized = 'true';
        let touchStartX = 0;
        let touchStartY = 0;
        let touchStartTime = 0;
        let isVerticalScroll = false;
        let isNavigating = false;

        form.addEventListener('touchstart', function (e) {
          if (isNavigating || e.touches.length !== 1) return;
          const target = e.touches[0].target;
          if (target.closest('input, textarea, select, button, a, .tabledrag-handle, [role="button"]')) return;
          touchStartX = e.touches[0].clientX;
          touchStartY = e.touches[0].clientY;
          touchStartTime = Date.now();
          isVerticalScroll = false;
        }, { passive: true });

        form.addEventListener('touchmove', function (e) {
          if (isNavigating || e.touches.length !== 1) return;
          const currentX = e.touches[0].clientX;
          const currentY = e.touches[0].clientY;
          const diffX = Math.abs(currentX - touchStartX);
          const diffY = Math.abs(currentY - touchStartY);
          if (diffY > diffX && diffY > 10) {
            isVerticalScroll = true;
          }
        }, { passive: true });

        form.addEventListener('touchend', function (e) {
          if (isNavigating || isVerticalScroll || e.changedTouches.length !== 1) return;
          const touchEndX = e.changedTouches[0].clientX;
          const touchEndY = e.changedTouches[0].clientY;
          const diffX = touchEndX - touchStartX;
          const diffY = touchEndY - touchStartY;
          const elapsedTime = Date.now() - touchStartTime;

          const minDistance = 45;
          const maxDuration = 650;

          if (elapsedTime <= maxDuration && Math.abs(diffX) >= minDistance && Math.abs(diffX) > Math.abs(diffY) * 1.4) {
            if (diffX > 0) {
              const prevButton = form.querySelector(
                '.webform-button--previous, [data-drupal-selector*="wizard-prev"], input[name="wizard_prev"], button[name="wizard_prev"], input[value="Anterior"]'
              );
              if (prevButton && !prevButton.disabled) {
                isNavigating = true;
                form.classList.add('slide-transition-prev');
                setTimeout(function () { prevButton.click(); }, 100);
              }
            } else {
              const nextButton = form.querySelector(
                '.webform-button--next, [data-drupal-selector*="wizard-next"], input[name="wizard_next"], button[name="wizard_next"], input[value="Siguiente"]'
              );
              if (nextButton && !nextButton.disabled) {
                isNavigating = true;
                form.classList.add('slide-transition-next');
                setTimeout(function () { nextButton.click(); }, 100);
              }
            }
          }
        }, { passive: true });
      }
    });
  }

  // Ejecución en Vanilla JS puro
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      initVanillaEnhancements(document);
    });
  } else {
    initVanillaEnhancements(document);
  }

  // Compatibilidad con Drupal.behaviors si el framework está presente
  if (typeof window !== 'undefined' && window.Drupal) {
    window.Drupal.behaviors = window.Drupal.behaviors || {};
    window.Drupal.behaviors.formularioEquidadSeguros = {
      attach: function (context) {
        initVanillaEnhancements(context);
      }
    };
  }
})();
