<?php $this->start('script'); ?>
<?php echo $this->fetch('script'); ?>
<script type="text/javascript">
$(function() {
    var newOptions = <?php echo $playlistOptions; ?>;
    $('#add-to-playlist-selecter').empty();
    $('#create-playlist-input input').val('');
    $.each(newOptions, function(key, value) {
        $('#add-to-playlist-selecter').append($('<option></option>').attr('value', key).text(value));
    });
    $('#add-to-playlist-selecter').selecter('destroy');
    $('#add-to-playlist-selecter').selecter({
        label: "<?php echo __('Select a playlist'); ?>"
    });
});
</script>
<?php $this->end(); ?>
<?php $this->start('playlist_form');?>
<?php echo $this->element('add_to_playlist');?>
<?php $this->end();?>
