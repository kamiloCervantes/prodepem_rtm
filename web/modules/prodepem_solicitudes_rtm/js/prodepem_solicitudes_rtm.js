/**
 * @file
 * prodepem_solicitudes_rtm behaviors.
 */

(function (Drupal, once) {

    'use strict';

    Drupal.behaviors.prodepemSolicitudesRtm = {
        attach: function (context, settings) {
            console.log('prodepem_solicitudes_rtm');
            const cdaWrapper = document.querySelector('#cda-wrapper');
            if (cdaWrapper) {
                const cdaSelect = cdaWrapper.querySelector('select');
                if (cdaSelect) {
                    cdaSelect.addEventListener('change', function () {
                        const tid = this.value;
                        const detailsContainer = document.querySelector('#detalles-cda-wrapper div');
                        console.log(detailsContainer);
                        if (tid) {
                            fetch('/ajax/get-cda-details/' + tid)
                                .then(response => response.json())
                                .then(data => {
                                    if (detailsContainer) {
                                        detailsContainer.innerHTML = `
                                            <div class="cda-card">
                                                <h3>${data.name}</h3>
                                                <div class="cda-info-item"><strong>Ciudad:</strong> ${data.field_ciudad}</div>
                                                <div class="cda-info-item"><strong>Departamento:</strong> ${data.field_departamento}</div>
                                                <div class="cda-info-item"><strong>Dirección:</strong> ${data.field_direccion}</div>
                                                <div class="cda-info-item"><strong>Teléfono:</strong> ${data.field_telefono}</div>
                                                <div class="cda-description">${data.description}</div>
                                            </div>
                                        `;
                                    }
                                })
                                .catch(error => console.error('Error:', error));
                        }
                        else {
                            detailsContainer.innerHTML = '';
                        }
                    });
                }
            }

        }
    };

}(Drupal, once));
