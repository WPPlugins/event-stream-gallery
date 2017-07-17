( function( $ ) {
    var esg_app = {
        gallery : {
            // esg_app.gallery.get_id();
            get_id : function() {
                var gallery_id = $('#esg_gallery_id').val() || 0;
                gallery_id = parseInt(gallery_id);

                if ( gallery_id <= 0 ) {
                    gallery_id = $('#post_ID').val() || 0;
                }
                
                gallery_id = parseInt(gallery_id);
                
                return gallery_id;
            },
            
            install_on_delete_hook : function () {
                // Delete from media
                $( '.esg_gallery_item_admin_delete_media_btn' ).off( 'click' ).on( 'click', function (e) {
                    e.preventDefault();

                    if ( !confirm('Are you sure you want to remove this item from the gallery?')) {
                        return false;
                    }

                    var media_id = $(this).data('media_id') || 0;

                    $('.esg_gallery_item_' + media_id).hide('slow').remove();

                    var url = esg_cfg.ajax_url;
                    var ajax_params = {
                        media_id : media_id,
                        gallery_id : esg_app.gallery.get_id(),
                        action : 'esg_admin_ajax_remove_image_from_gallery'
                    };

                    $.ajax({
                        url: url,
                        method: "POST",
                        data: ajax_params
                    }).done(function (json) {
                        if ( json.status ) {
                           $('.esg_gallery_images_list').append(json.html);
                        } else {
                            alert(json.msg);
                        }
                    });

                    return false;
                });
            },
            
            install_uploader : function() {
                if ( $('#esg_upload').length ) {
                    var gallery_id = esg_app.gallery.get_id();
                    var url = esg_cfg.ajax_url + '?action=esg_ajax_upload&gallery_id=' + encodeURI(gallery_id);
                    
                    $('.esg_fake_upload_button').on('click', function () {
                       $('#esg_upload').click(); 
                    });

                    $( '.uk-progress .uk-progress-bar' ).empty();

                    $('#esg_upload').fileupload({
                        url: url,
                        dataType: 'json',
                        autoUpload: true,
                        sequentialUploads: true,
                        acceptFileTypes: /(\.|\/)(gif|jpe?g|png)$/i,
                        maxFileSize: 15728640, // 15mb
                        // Enable image resizing, except for Android and Opera,
                        // which actually support image resizing, but fail to
                        // send Blob objects via XHR requests:
                        disableImageResize: /Android(?!.*Chrome)|Opera/
                            .test(window.navigator.userAgent),
                        previewMaxWidth: 150,
                        previewMaxHeight: 150,
                        previewCrop: true,
                        add: function (e, data) {
                            data.submit();
                        },
                        done: function (e, data) {
                            //console.log(data.result);
                            /*$.each(data.result.files, function (index, file) {
                                //$('<p/>').text(file.name).appendTo('#files');
                            });*/

                            $('.esg_result').html(data.result.html);
                            
                            if ( data.result.status ) {
                                $('.esg_gallery_images_nothing_found').remove();

                                if ($('.esg_gallery_images_list').length) {
                                   $('.esg_gallery_images_list').append(data.result.media_html);
                                }

                                // Clear the progress with a delay
                                setTimeout( function () {
                                    $('.uk-progress .uk-progress-bar').css( 'width', '0%' );
                                    $('.uk-progress .uk-progress-bar').empty();
                                    $('.esg_result').empty();
                                }, 3500 );
                            }
                        },
                        progressall: function (e, data) {
                            var progress = parseInt(data.loaded / data.total * 100, 10);
                            var prog_val = progress + '%';
                            $( '.uk-progress .uk-progress-bar').css( 'width', prog_val );

                            if ( progress <= 0 ) {
                                $( '.uk-progress .uk-progress-bar' ).empty();
                            } else {
                                $( '.uk-progress .uk-progress-bar' ).text( prog_val );
                            }
                        }
                    }).prop('disabled', !$.support.fileInput)
                      .parent().addClass($.support.fileInput ? undefined : 'disabled');
                }
            }
        },

        util : {
            // esg_app.util.msg();
            msg : function( msg, status, container ) {
                msg = msg || '';
                container = container || '.result';
                container = jQuery( container );

                var cls = 'app_success';

                if ( status == 0 ) {
                    cls = 'app_error';
                } else if ( status == 2 ) {
                    cls = 'app_warning';
                }

                jQuery(container).removeClass( 'app_success app_error app_warning' );
                jQuery(container).html( msg );

                if ( msg != '' ) { // If no message is passed just clear
                    jQuery( container ).addClass( cls );
                }
            }
        }
    };
    
    ////////////////////////////////////////////////////////////////////////////
    // Admin
    esg_app.gallery.install_on_delete_hook();
    // /Admin
    ////////////////////////////////////////////////////////////////////////////
    setTimeout( function () {
        esg_app.gallery.install_uploader();
    }, 500);
    
    /**
    * Show/upload Media based on file type.
    * @see https://github.com/aubreypwd/wp-forums/
    * @see https://wordpress.stackexchange.com/questions/124990/wordpress-media-manager-multiple-selection-output
    * @see http://learnwebtutorials.com/code-to-open-media-gallery-dialog-in-wordpress
    * @see https://wordpress.stackexchange.com/questions/85442/is-it-possible-to-reuse-wp-media-editor-modal-for-dialogs-other-than-media
    */
   $.fn.esg_media_lib_picker = function() {
        var esg_media_lib_picker = this; // This makes this easier to understand.

        // The buttons in wp-forums.php aubreypwd_wp_forums_metabox_add_media_test_display().
        esg_media_lib_picker.$buttons = $( '.esg_pick_images_from_media_library' );

        // When we click on of the buttons.
        esg_media_lib_picker.$buttons.on( 'click', function( event ) {
            event.preventDefault(); // Don't "submit" when clicked.

            var button = this; // The button that was clicked.
            var type   = $( button ).data( 'type' ); // The type on the data-type="" attribute.

            // Open the wp.media frame.
            var frame = wp.media( {
                title : 'Add gallery image(s)',
                multiple: true, // Change to true for multiple file selections.
                button : { text : 'Add to Gallery' },

                /*
                 * Here is where the main magic happens.
                 * We take the type, e.g. video, image, audio,
                 * and we send it to library.type which only
                 * shows the files of that type.
                 */
                library: {
                    type : type 
                }
            } );

            // When a file is selected.
            frame.on( 'select', function() {
                 // Get that file.
                 var gallery_id = esg_app.gallery.get_id();
                 var attachment = frame.state().get('selection').first().toJSON();

                var ajax_params = {
                    attachment_ids : [],
                    images : [],
                    gallery_id : gallery_id,
                    action : 'esg_admin_ajax_add_image_to_gallery'
                };

                var selection = frame.state().get('selection');
                selection.map( function( attachment ) {
                    var json = attachment.toJSON();
//                    ajax_params.images.push(json);
                    ajax_params.attachment_ids.push(json.id);
                });

                var url = esg_cfg.ajax_url;
    
                $.ajax({
                    url: url,
                    method: "POST",
                    data: ajax_params
                }).done(function (json) {
                    if ( json.status ) {
                       $('.esg_gallery_images_list').append(json.html);
                       esg_app.gallery.install_on_delete_hook();
                    } else {
                        alert(json.msg);
                    }
                });
            } );

            // Open the frame, we've got it setup.
            frame.open();
        } );
    };

    $( document ).ready( $.fn.esg_media_lib_picker );

} ( jQuery ) );
