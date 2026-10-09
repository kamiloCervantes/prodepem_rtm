/**
 * @file
 * Mobile swipe navigation and styling enhancements for formulario_equidad_seguros.
 */

(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.formularioEquidadSeguros = {
    attach: function (context) {
      const forms = once(
        'equidad-swipe-nav',
        '.webform-submission-formulario-equidad-seguros-add-form, .formulario-equidad-seguros',
        context
      );

      forms.forEach(function (form) {
        // Asignación de datos ASPU cuando selector_tomador es 2 (campo oculto con valor predeterminado 2)
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
            const field = form.querySelector(`[name="${fieldName}"]`);
            if (field && (!field.value || field.value.trim() === '')) {
              field.value = aspuFields[fieldName];
            }
          });
        }
        // Homologar estilo de "?Est? interesado en otros productos?" con "Seguros adicionales"
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

        // Asegurar alineaci?n perfecta de la primera l?nea de texto con la casilla de verificaci?n
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

        // Asegurar ancho suficiente para la columna "Tipo de documento" y validar total de porcentaje == 100 en tablas de beneficiarios
        const tables = form.querySelectorAll('.webform-multiple-table, table');
        tables.forEach(function (table) {
          const headers = Array.from(table.querySelectorAll('thead th, tr:first-child th'));
          let percentColIndex = -1;

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
            if (headerText === '%' || headerText.includes('porcent') || headerText.includes('participa')) {
              percentColIndex = colIndex;
            }
          });

          // Validación de total porcentaje == 100% cuando la tabla de beneficiarios pierde el foco
          const tableWrapper = table.closest('.webform-multiple-table-wrapper') || table.closest('.form-item') || table.parentElement || table;
          const wrapperText = (tableWrapper ? tableWrapper.textContent : '').toLowerCase();
          const isBeneficiariosTable = percentColIndex !== -1 || wrapperText.includes('beneficiar') || (table.getAttribute('data-drupal-selector') || '').includes('beneficiar') || (table.id || '').includes('beneficiar');

          if (isBeneficiariosTable) {
            const isTableInCurrentStep = function () {
              if (!table) return false;
              if (table.offsetParent === null) {
                return false;
              }
              const rect = table.getBoundingClientRect();
              return rect.width > 0 && rect.height > 0;
            };

            const isFinalSubmitButton = function (btn) {
              if (!btn) return false;
              const name = (btn.name || '').toLowerCase();
              const drupalSelector = (btn.getAttribute('data-drupal-selector') || '').toLowerCase();
              const cls = btn.className || '';
              if (cls.includes('webform-button--next') || cls.includes('webform-button--previous') ||
                  name.includes('wizard_next') || name.includes('wizard_prev') ||
                  drupalSelector.includes('wizard-next') || drupalSelector.includes('wizard-prev')) {
                return false;
              }
              return cls.includes('webform-button--submit') || name === 'op' || drupalSelector.includes('submit') || btn.type === 'submit';
            };

            const getPercentInputs = function () {
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
              return Array.from(table.querySelectorAll('input[name*="porcent"], input[name*="porcentaje"], input[data-drupal-selector*="porcent"]'));
            };

            const calculateBeneficiariosTotal = function () {
              const inputs = getPercentInputs();
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

              // Verificar si alguna fila tiene campos diligenciados
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
            };

            // Contenedor de mensaje de validación
            let feedbackEl = tableWrapper.querySelector('.beneficiarios-porcentaje-feedback');
            if (!feedbackEl) {
              feedbackEl = document.createElement('div');
              feedbackEl.className = 'beneficiarios-porcentaje-feedback';
              feedbackEl.style.display = 'none';
              table.parentNode.insertBefore(feedbackEl, table.nextSibling);
            }

            let tableHasBeenFocused = false;

            const validateBeneficiariosPorcentaje = function (showValidation) {
              const res = calculateBeneficiariosTotal();

              // Si la tabla no tiene ninguna fila con datos ni porcentajes escritos
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
            };

            // Detectar cuando el foco entra a la tabla
            tableWrapper.addEventListener('focusin', function () {
              tableHasBeenFocused = true;
            });

            // Detectar cuando la tabla pierde el foco (solo validar si la tabla está en el paso actual)
            tableWrapper.addEventListener('focusout', function () {
              setTimeout(function () {
                const activeEl = document.activeElement;
                // Si el foco se trasladó a otro control dentro de la misma tabla, no validar todavía
                if (activeEl && tableWrapper.contains(activeEl)) {
                  return;
                }
                // La tabla ha perdido el foco por completo; validar solo en el paso 2 (cuando la tabla está visible)
                if (tableHasBeenFocused && isTableInCurrentStep()) {
                  validateBeneficiariosPorcentaje(true);
                }
              }, 80);
            });

            // Actualización dinámica en tiempo real si ya se mostró mensaje de validación
            tableWrapper.addEventListener('input', function () {
              if (feedbackEl.style.display !== 'none' && isTableInCurrentStep()) {
                validateBeneficiariosPorcentaje(true);
              }
            });

            // Botones de navegación y envío: permitir navegar libremente por el wizard
            const forwardButtons = form.querySelectorAll(
              '.webform-button--next, [data-drupal-selector*="wizard-next"], input[name="wizard_next"], button[name="wizard_next"], .webform-button--submit, [data-drupal-selector*="submit"], input[type="submit"]'
            );

            forwardButtons.forEach(function (btn) {
              btn.addEventListener('click', function (e) {
                const isCurrent = isTableInCurrentStep();
                const isFinal = isFinalSubmitButton(btn);
                const res = calculateBeneficiariosTotal();

                // Si está en el paso 2 y presiona Siguiente, mostrar la advertencia visual si hay error pero PERMITIR la navegación
                if (isCurrent && !isFinal) {
                  if (res.hasAnyValue || res.hasAnyRowData) {
                    if (res.sum !== 100) {
                      validateBeneficiariosPorcentaje(true);
                      // NO se bloquea con preventDefault para permitir navegar libremente por el wizard
                    }
                  }
                  return;
                }

                // Al final del formulario (envío final): si el error persiste, bloquear el envío
                if (isFinal) {
                  if (res.hasAnyValue || res.hasAnyRowData) {
                    if (res.sum !== 100) {
                      e.preventDefault();
                      e.stopPropagation();
                      alert('Atención: El porcentaje total de los beneficiarios debe ser igual a 100% (la suma actual es ' + res.sum + '%). Por favor revise el Paso 2 de beneficiarios antes de enviar la solicitud.');
                    }
                  }
                }
              }, true);
            });
          }
        });

        // Swipe Navigation
        let touchStartX = 0;
        let touchStartY = 0;
        let touchStartTime = 0;
        let isVerticalScroll = false;
        let isNavigating = false;

        form.addEventListener('touchstart', function (e) {
          if (isNavigating || e.touches.length !== 1) {
            return;
          }

          const target = e.touches[0].target;
          // Ignore touches on interactive controls so normal user interaction is preserved
          if (target.closest('input, textarea, select, button, a, .tabledrag-handle, [role="button"]')) {
            return;
          }

          touchStartX = e.touches[0].clientX;
          touchStartY = e.touches[0].clientY;
          touchStartTime = Date.now();
          isVerticalScroll = false;
        }, { passive: true });

        form.addEventListener('touchmove', function (e) {
          if (isNavigating || e.touches.length !== 1) {
            return;
          }

          const currentX = e.touches[0].clientX;
          const currentY = e.touches[0].clientY;
          const diffX = Math.abs(currentX - touchStartX);
          const diffY = Math.abs(currentY - touchStartY);

          // If vertical movement is dominant early on, mark as vertical scrolling
          if (diffY > diffX && diffY > 10) {
            isVerticalScroll = true;
          }
        }, { passive: true });

        form.addEventListener('touchend', function (e) {
          if (isNavigating || isVerticalScroll || e.changedTouches.length !== 1) {
            return;
          }

          const touchEndX = e.changedTouches[0].clientX;
          const touchEndY = e.changedTouches[0].clientY;
          const diffX = touchEndX - touchStartX;
          const diffY = touchEndY - touchStartY;
          const elapsedTime = Date.now() - touchStartTime;

          const minDistance = 45; // Pixels
          const maxDuration = 650; // Milliseconds

          // Validate horizontal swipe gesture
          if (elapsedTime <= maxDuration && Math.abs(diffX) >= minDistance && Math.abs(diffX) > Math.abs(diffY) * 1.4) {
            if (diffX > 0) {
              // Slide de izquierda a derecha -> Anterior
              const prevButton = form.querySelector(
                '.webform-button--previous, [data-drupal-selector*="wizard-prev"], input[name="wizard_prev"], button[name="wizard_prev"], input[value="Anterior"]'
              );

              if (prevButton && !prevButton.disabled) {
                isNavigating = true;
                form.classList.add('slide-transition-prev');
                setTimeout(function () {
                  prevButton.click();
                }, 100);
              }
            } else {
              // Slide de derecha a izquierda -> Siguiente
              const nextButton = form.querySelector(
                '.webform-button--next, [data-drupal-selector*="wizard-next"], input[name="wizard_next"], button[name="wizard_next"], input[value="Siguiente"]'
              );

              if (nextButton && !nextButton.disabled) {
                isNavigating = true;
                form.classList.add('slide-transition-next');
                setTimeout(function () {
                  nextButton.click();
                }, 100);
              }
            }
          }
        }, { passive: true });
      });
    }
  };
})(Drupal, once);