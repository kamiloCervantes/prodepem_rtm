/**
 * @file
 * Mobile swipe navigation, dynamic table percentage calculator, and styling enhancements
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

    const byName = table.querySelectorAll('input[name*="porcent"], input[name*="porcentaje"], input[data-drupal-selector*="porcent"]');
    if (byName.length > 0) {
      return Array.from(byName);
    }

    return [];
  }

  /**
   * Calcula el total del porcentaje asignado en la tabla.
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
      rowsCount: rows.length,
      hasAnyValue: hasAnyValue,
      hasAnyRowData: hasAnyRowData
    };
  }

  /**
   * Obtiene o crea el elemento de feedback debajo de la tabla.
   */
  function getOrCreateFeedbackEl(table) {
    let feedbackEl = table.parentNode.querySelector('.beneficiarios-porcentaje-feedback');
    if (!feedbackEl) {
      feedbackEl = document.createElement('div');
      feedbackEl.className = 'beneficiarios-porcentaje-feedback';
      table.parentNode.insertBefore(feedbackEl, table.nextSibling);
    }
    return feedbackEl;
  }

  /**
   * Actualiza el mensaje visual con el total del porcentaje debajo de la tabla.
   * Se muestra advertencia cuando el total es diferente a 100.
   */
  function updateBeneficiariosTotalMessage(table) {
    if (!isBeneficiariosTable(table)) return;

    const res = calculateBeneficiariosTotal(table);
    const feedbackEl = getOrCreateFeedbackEl(table);

    // Si la tabla no tiene filas o está completamente en blanco
    if (res.rowsCount === 0 || (!res.hasAnyValue && !res.hasAnyRowData)) {
      feedbackEl.style.display = 'none';
      table.classList.remove('beneficiarios-table--error');
      res.inputs.forEach(function (inp) {
        inp.classList.remove('beneficiarios-porcentaje-input--error');
        inp.classList.remove('beneficiarios-porcentaje-input--valid');
      });
      return;
    }

    if (res.sum === 100) {
      // Correcto: Total igual a 100%
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
    } else {
      // Advertencia: Total diferente a 100%
      feedbackEl.className = 'beneficiarios-porcentaje-feedback beneficiarios-porcentaje-feedback--error';
      feedbackEl.innerHTML = '<span class="feedback-icon">⚠</span> Total porcentaje asignado: <strong>' + res.sum + '%</strong>. La suma debe ser igual a <strong>100%</strong>.';
      feedbackEl.style.display = 'flex';
      table.classList.add('beneficiarios-table--error');
      res.inputs.forEach(function (inp) {
        inp.classList.remove('beneficiarios-porcentaje-input--valid');
        inp.classList.add('beneficiarios-porcentaje-input--error');
      });
    }
  }

  // =========================================================================
  // Observadores y Eventos en tiempo real para calcular automáticamente
  // =========================================================================

  // 1. Recalcular al escribir o modificar cualquier campo de la tabla
  document.addEventListener('input', function (e) {
    const table = e.target.closest('table');
    if (table && isBeneficiariosTable(table)) {
      updateBeneficiariosTotalMessage(table);
    }
  });

  document.addEventListener('change', function (e) {
    const table = e.target.closest('table');
    if (table && isBeneficiariosTable(table)) {
      updateBeneficiariosTotalMessage(table);
    }
  });

  // 2. Recalcular al perder el foco la tabla
  document.addEventListener('focusout', function (e) {
    const table = e.target.closest('table');
    if (table && isBeneficiariosTable(table)) {
      setTimeout(function () {
        updateBeneficiariosTotalMessage(table);
      }, 50);
    }
  });

  // 3. Recalcular cuando se hace clic en botones para agregar o eliminar filas
  document.addEventListener('click', function (e) {
    const target = e.target;
    // Si se hace clic en botones de agregar fila, eliminar fila o drag
    if (target.matches('input[type="submit"], button, .button, [value*="Agregar"], [value*="add"], [value*="Eliminar"], [value*="remove"]') ||
        target.closest('.webform-multiple-add, .webform-multiple-table-operations')) {
      setTimeout(function () {
        const tables = document.querySelectorAll('.webform-multiple-table, table');
        tables.forEach(function (table) {
          if (isBeneficiariosTable(table)) {
            updateBeneficiariosTotalMessage(table);
          }
        });
      }, 250);
    }
  });

  // 4. Observar mutaciones en el DOM para cuando Drupal inserte nuevas filas <tr> en la tabla
  function attachTableObserver(table) {
    if (table.dataset.observerAttached) return;
    table.dataset.observerAttached = 'true';

    const observer = new MutationObserver(function () {
      updateBeneficiariosTotalMessage(table);
    });

    const tbody = table.querySelector('tbody') || table;
    observer.observe(tbody, { childList: true, subtree: true });
  }


  // =========================================================================
  // Directiva de Formato Monetario (Moneda Colombiana COP: $20.000.000)
  // =========================================================================

  /**
   * Asegura que el input sea de tipo text y remueva restricciones numéricas nativas de HTML5.
   * Si el navegador bloquea cambiar input.type directamente, reemplaza el nodo con un clon textfield.
   */
  function prepareCurrencyInput(input) {
    if (!input) return input;
    if (input.dataset.currencyReady === 'true') return input;

    if (input.type === 'number' || input.getAttribute('type') === 'number') {
      try {
        input.type = 'text';
      } catch (e) {
        try {
          const replacement = document.createElement('input');
          for (let i = 0; i < input.attributes.length; i++) {
            const attr = input.attributes[i];
            if (attr.name !== 'type' && attr.name !== 'min' && attr.name !== 'max' && attr.name !== 'step') {
              replacement.setAttribute(attr.name, attr.value);
            }
          }
          replacement.setAttribute('type', 'text');
          replacement.value = input.value;
          if (input.parentNode) {
            input.parentNode.replaceChild(replacement, input);
            input = replacement;
          }
        } catch (err) {}
      }
      input.removeAttribute('step');
      input.removeAttribute('min');
      input.removeAttribute('max');
    }

    input.setAttribute('inputmode', 'numeric');
    input.setAttribute('autocomplete', 'off');
    input.classList.add('input-formato-moneda');
    input.dataset.currencyReady = 'true';
    return input;
  }

  /**
   * Formatea un valor numérico como monto monetario ($20.000.000 con puntos y signo de peso).
   */
  function formatMoneyCOP(value) {
    if (value === null || value === undefined) return '';
    const digits = String(value).replace(/\D/g, '');
    if (!digits) return '';
    const cleanDigits = digits.replace(/^0+(?=\d)/, '');
    const withDots = cleanDigits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return '$' + withDots;
  }

  /**
   * Verifica si un campo de entrada corresponde a un campo monetario.
   */
  function isCurrencyField(input) {
    if (!input || input.type === 'hidden' || input.type === 'submit' || input.type === 'button') {
      return false;
    }
    const name = (input.name || '').toLowerCase();
    const selector = (input.getAttribute('data-drupal-selector') || '').toLowerCase();
    const id = (input.id || '').toLowerCase();
    const className = (input.className || '').toLowerCase();
    const isExplicit = input.hasAttribute('data-currency-mask') || className.includes('js-currency-mask') || className.includes('input-formato-moneda');

    return isExplicit ||
      name.includes('valor_asegurado_solicitado') ||
      name.includes('total_valor_asegurado') ||
      name === 'valor_asegurado' ||
      selector.includes('valor-asegurado-solicitado') ||
      selector.includes('total-valor-asegurado') ||
      selector.includes('valor-asegurado') ||
      id.includes('valor-asegurado-solicitado') ||
      id.includes('total-valor-asegurado');
  }

  /**
   * Aplica la máscara monetaria manteniendo la posición adecuada del cursor.
   */
  function applyCurrencyMask(input) {
    if (!input) return;
    input = prepareCurrencyInput(input);

    const rawVal = input.value || '';
    const digits = rawVal.replace(/\D/g, '');

    if (!digits) {
      input.value = '';
      return;
    }

    const formatted = formatMoneyCOP(rawVal);
    if (input.value !== formatted) {
      let oldSel = input.selectionEnd;
      if (oldSel === null || oldSel === undefined) {
        oldSel = rawVal.length;
      }
      const digitsBefore = rawVal.slice(0, oldSel).replace(/\D/g, '').length;

      input.value = formatted;

      let newPos = formatted.length;
      let count = 0;
      for (let i = 0; i < formatted.length; i++) {
        if (/\d/.test(formatted[i])) {
          count++;
          if (count === digitsBefore) {
            newPos = i + 1;
            break;
          }
        }
      }
      if (digitsBefore === 0) {
        newPos = formatted ? 1 : 0;
      }
      try {
        input.setSelectionRange(newPos, newPos);
      } catch (err) {}
    }
  }

  /**
   * Inicializa la directiva de máscara de moneda en los inputs seleccionados.
   */
  function initCurrencyInputs(container) {
    const root = container || document;
    const inputs = root.querySelectorAll(
      'input[name="valor_asegurado_solicitado"], input[data-drupal-selector*="valor-asegurado-solicitado"], ' +
      'input[name="total_valor_asegurado"], input[data-drupal-selector*="total-valor-asegurado"], ' +
      'input[name="valor_asegurado"], input[data-currency-mask], .js-currency-mask, .input-formato-moneda'
    );

    inputs.forEach(function (input) {
      input = prepareCurrencyInput(input);
      if (input.dataset.currencyMaskInitialized === 'true') return;
      input.dataset.currencyMaskInitialized = 'true';

      // Si tiene un valor preexistente, formatearlo de inmediato
      if (input.value && input.value.trim() !== '') {
        input.value = formatMoneyCOP(input.value);
      }

      input.addEventListener('input', function () {
        applyCurrencyMask(input);

        // Si es valor_asegurado_solicitado, sincronizar con total_valor_asegurado
        const name = (input.name || '').toLowerCase();
        const selector = (input.getAttribute('data-drupal-selector') || '').toLowerCase();
        if (name.includes('valor_asegurado_solicitado') || selector.includes('valor-asegurado-solicitado')) {
          const form = input.closest('form') || document;
          const totalInput = form.querySelector('input[name="total_valor_asegurado"], input[data-drupal-selector*="total-valor-asegurado"]');
          if (totalInput && (!totalInput.value || totalInput.dataset.autoSynced === 'true')) {
            prepareCurrencyInput(totalInput);
            totalInput.value = input.value;
            totalInput.dataset.autoSynced = 'true';
            applyCurrencyMask(totalInput);
          }
        }
      });

      // Si el usuario edita total_valor_asegurado manualmente, desactivar auto-sincronización
      const name = (input.name || '').toLowerCase();
      const selector = (input.getAttribute('data-drupal-selector') || '').toLowerCase();
      if (name.includes('total_valor_asegurado') || selector.includes('total-valor-asegurado')) {
        input.addEventListener('keydown', function () {
          input.dataset.autoSynced = 'false';
        });
      }

      // Manejar retroceso en separadores de miles con punto
      input.addEventListener('keydown', function (e) {
        if (e.key === 'Backspace') {
          const cur = input.selectionStart;
          const end = input.selectionEnd;
          if (cur === end && cur > 1) {
            const charBefore = input.value[cur - 1];
            if (charBefore === '.') {
              e.preventDefault();
              const beforeSlice = input.value.slice(0, cur - 1);
              const afterSlice = input.value.slice(cur);
              const digitsBefore = beforeSlice.replace(/\D/g, '');
              const digitsAfter = afterSlice.replace(/\D/g, '');
              const newDigits = digitsBefore.slice(0, -1) + digitsAfter;
              input.value = formatMoneyCOP(newDigits);

              const targetDigits = Math.max(0, digitsBefore.length - 1);
              let count = 0;
              let newPos = input.value ? 1 : 0;
              for (let i = 0; i < input.value.length; i++) {
                if (/\d/.test(input.value[i])) {
                  count++;
                  if (count === targetDigits) {
                    newPos = i + 1;
                    break;
                  }
                }
              }
              try {
                input.setSelectionRange(newPos, newPos);
              } catch (err) {}
              input.dispatchEvent(new Event('input', { bubbles: true }));
            }
          }
        }
      });

      input.addEventListener('blur', function () {
        if (input.value && input.value.trim() !== '') {
          input.value = formatMoneyCOP(input.value);
        }
      });
    });
  }

  // Preparar proactivamente los inputs al hacer focus, clic o touch antes de que ocurra la primera pulsación de tecla
  document.addEventListener('focusin', function (e) {
    if (e.target && isCurrencyField(e.target)) {
      prepareCurrencyInput(e.target);
    }
  }, true);

  document.addEventListener('pointerdown', function (e) {
    if (e.target && isCurrencyField(e.target)) {
      prepareCurrencyInput(e.target);
    }
  }, true);

  // Delegación global en captura para asegurar formateo instantáneo incluso en elementos dinámicos
  document.addEventListener('input', function (e) {
    const target = e.target;
    if (target && isCurrencyField(target)) {
      applyCurrencyMask(target);
    }
  }, true);

  /**
   * Inicialización de mejoras estéticas, cálculos y gestos en Vanilla JS.
   */
  function initVanillaEnhancements(root) {
    const context = root || document;
    const forms = context.querySelectorAll ? context.querySelectorAll('.webform-submission-formulario-equidad-seguros-add-form, .webform-submission-formulario-equidad-seguros-prode-add-form, .formulario-equidad-seguros, .formulario-equidad-seguros-prode, form[id*="formulario-equidad-seguros"]') : [];

    forms.forEach(function (form) {
      // 1. Datos automáticos del tomador (PRODEPEM o ASPU)
      const isProdeForm = form.classList.contains('webform-submission-formulario-equidad-seguros-prode-add-form') ||
        form.classList.contains('formulario-equidad-seguros-prode') ||
        (form.id && form.id.includes('formulario-equidad-seguros-prode')) ||
        (form.getAttribute('data-drupal-selector') && form.getAttribute('data-drupal-selector').includes('formulario-equidad-seguros-prode'));

      if (isProdeForm) {
        const prodepemFields = {
          'tomador': 'PRODEPEM S.A.S',
          'c_c_nit': '900.582.164-1',
          'nit_tomador': '900.582.164-1',
          'direccion': 'Cra 6 # 77-305',
          'direccion_tomador': 'Cra 6 # 77-305',
          'ciudad': 'Montería',
          'ciudad_tomador': 'Montería',
          'telefono': '3242560489',
          'telefono_tomador': '3242560489'
        };
        Object.keys(prodepemFields).forEach(function (fieldName) {
          const field = form.querySelector('[name="' + fieldName + '"]');
          if (field && (!field.value || field.value.trim() === '')) {
            field.value = prodepemFields[fieldName];
          }
        });
      } else {
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
      }

      // 1.1. Directiva de formato monetario para campos de valor asegurado ($20.000.000)
      initCurrencyInputs(form);

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

      // 2.1. Omitir la opción "- Ninguno -" en selectores con opciones Sí / No
      const cleanNingunoSelects = function (container) {
        const selects = container.querySelectorAll('select');
        selects.forEach(function (select) {
          const options = Array.from(select.options);
          const hasYes = options.some(function (opt) {
            const val = (opt.value || '').toLowerCase().trim();
            const txt = (opt.textContent || '').toLowerCase().trim();
            return val === 'si' || val === 'sí' || val === '1' || txt === 'sí' || txt === 'si';
          });
          const hasNo = options.some(function (opt) {
            const val = (opt.value || '').toLowerCase().trim();
            const txt = (opt.textContent || '').toLowerCase().trim();
            return val === 'no' || val === '0' || txt === 'no';
          });

          if (hasYes && hasNo) {
            for (let i = select.options.length - 1; i >= 0; i--) {
              const opt = select.options[i];
              const txt = (opt.textContent || '').toLowerCase().trim();
              const val = (opt.value || '').toLowerCase().trim();
              if (val === '' || val === '_none' || txt.includes('ninguno') || txt.includes('none') || txt === '-') {
                opt.remove();
              }
            }
          }
        });
      };
      cleanNingunoSelects(form);

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

      // 4. Tablas de beneficiarios: columna tipo doc, observador de nuevas filas y cálculo inicial
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

        if (isBeneficiariosTable(table)) {
          attachTableObserver(table);
          updateBeneficiariosTotalMessage(table);
        }
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
      initCurrencyInputs(document);
    });
  } else {
    initVanillaEnhancements(document);
    initCurrencyInputs(document);
  }

  // Compatibilidad con Drupal.behaviors si el framework está presente
  if (typeof window !== 'undefined' && window.Drupal) {
    window.Drupal.behaviors = window.Drupal.behaviors || {};
    window.Drupal.behaviors.formularioEquidadSeguros = {
      attach: function (context) {
        initVanillaEnhancements(context);
        initCurrencyInputs(context);
      }
    };
  }
})();
