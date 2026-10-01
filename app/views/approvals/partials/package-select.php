<?php /** @var array $packages  @var mixed $selected  @var array $errors */
echo form_select('approval-form', 'packaging_config', 'Package (labels)', $packages, $selected ?? '', $errors ?? [], ['blank' => $packages === [] ? 'No packages for this product' : 'Not tied to a package', 'name' => 'packaging_configuration_id', 'help' => 'Label approvals only.']);
