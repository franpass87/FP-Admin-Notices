<?php
/**
 * REST controller for updating notice dismissal state.
 */
declare(strict_types=1);

namespace FP\AdminNotices\REST;

use FP\AdminNotices\Support\User_Dismissed_Store;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

use function __;
use function array_filter;
use function array_unique;
use function array_values;
use function call_user_func;
use function count;
use function is_array;
use function is_string;
use function register_rest_route;
use function rest_authorization_required_code;
use function rest_ensure_response;
use function rest_sanitize_boolean;
use function sanitize_text_field;

/**
 * Controller responsible for REST endpoints used by the UI.
 */
class Notice_State_Controller extends WP_REST_Controller
{
    /**
     * Callback used to confirm if the current user can interact with the panel.
     *
     * @var callable
     */
    private $can_view_callback;

    private User_Dismissed_Store $store;

    public function __construct( callable $can_view_callback, User_Dismissed_Store $store )
    {
        $this->namespace          = 'fp-admin-notices/v1';
        $this->rest_base          = 'notices';
        $this->can_view_callback  = $can_view_callback;
        $this->store              = $store;
    }

    /**
     * Register REST routes.
     */
    public function register_routes(): void
    {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'update_item' ],
                    'permission_callback' => [ $this, 'permissions_check' ],
                    'args'                => [
                        'notice_id' => [
                            'type'     => 'string',
                            'required' => false,
                        ],
                        'notice_ids' => [
                            'type'     => 'array',
                            'items'    => [
                                'type' => 'string',
                            ],
                            'required' => false,
                        ],
                        'dismissed' => [
                            'type'    => 'boolean',
                            'default' => true,
                        ],
                    ],
                ],
            ]
        );
    }

    /**
     * Verify permissions for the route.
     *
     * @param WP_REST_Request $request Incoming request.
     * @return bool|WP_Error
     */
    public function permissions_check( WP_REST_Request $request ): bool|WP_Error
    {
        if ( (bool) call_user_func( $this->can_view_callback ) ) {
            return true;
        }

        return new WP_Error(
            'fp_admin_notices_forbidden',
            __( 'Non hai i permessi per gestire le notifiche.', 'fp-admin-notices' ),
            [ 'status' => rest_authorization_required_code() ]
        );
    }

    /**
     * Update notice state based on the request payload.
     */
    public function update_item( WP_REST_Request $request ): WP_REST_Response|WP_Error
    {
        $raw_dismissed = $request->get_param( 'dismissed' );
        $dismissed     = null === $raw_dismissed ? true : rest_sanitize_boolean( $raw_dismissed );

        $notice_ids = [];

        $notice_ids_param = $request->get_param( 'notice_ids' );
        if ( is_array( $notice_ids_param ) ) {
            foreach ( $notice_ids_param as $notice_id ) {
                $notice_id = sanitize_text_field( (string) $notice_id );
                if ( '' !== $notice_id ) {
                    $notice_ids[] = $notice_id;
                }
            }
        }

        $single_notice = $request->get_param( 'notice_id' );
        if ( is_string( $single_notice ) && '' !== $single_notice ) {
            $notice_ids[] = sanitize_text_field( $single_notice );
        }

        $notice_ids = array_values( array_unique( array_filter( $notice_ids ) ) );

        if ( empty( $notice_ids ) ) {
            return new WP_Error(
                'fp_admin_notices_missing_notice',
                __( 'Nessuna notifica specificata.', 'fp-admin-notices' ),
                [ 'status' => 400 ]
            );
        }

        foreach ( $notice_ids as $notice_id ) {
            $this->store->update( $notice_id, (bool) $dismissed );
        }

        $response = [
            'notice_ids' => $notice_ids,
            'dismissed'  => (bool) $dismissed,
        ];

        if ( 1 === count( $notice_ids ) ) {
            $response['notice_id'] = $notice_ids[0];
        }

        return rest_ensure_response( $response );
    }
}
