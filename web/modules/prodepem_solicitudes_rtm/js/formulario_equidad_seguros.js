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

        // Asegurar ancho suficiente para la columna "Tipo de documento" en tablas de beneficiarios
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