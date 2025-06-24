

<template>
<VCard>
<template>   
<div class="row" id="basic-table">
    <div class="col-12">
        <div class="card">
            <div class="card-body card-dashboard">
                <!--Pagar rentabilidad boton-->
                <div class="float-end">
                    <button class="btn btn-danger" id="btnModalCartera">Pagar Rentabilidad</button>
                </div>
                <!--Pagar rentabilidad boton End-->
                <!--Pagar rentabilidad title-->
                <div class="card-title">
                    <h4>Rentabilidad</h4>
                </div>
                <!--Pagar rentabilidad title end-->
                <div class="table-responsive">
                <table class="table  nowrap scroll-horizontal-vertical myTable table-striped w-100">
                        <thead class="">
                            <tr class="text-center">
                                <th>ID</th>
                                <th>Monto</th>
                                <th>% utilidad</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            
                            <tr class="text-center">
                                <td>
                                    
                                </td>

                                <td>
                                    
                                </td>
                                <td>
                                    
                                </td>
                                <td>
                                    
                                </td>
                            </tr>
                            
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Administrador de cartera-->

<!-- Vertical modal -->
<div class="vertical-modal-ex">
    <!-- Modal -->
    <div class="modal fade" id="ModalCartera" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalCenterTitle">Porcentaje de rentabilidad</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="porcentaje" class="form-label">Porcentaje</label>
                            <input type="number" class="form-control" id="porcentaje" aria-describedby="porcentaje" name="porcentaje" step="any">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>           
</template>
</VCard>
</template>
