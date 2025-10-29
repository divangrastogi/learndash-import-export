<?php
/**
 * Data mapping functionality.
 *
 * @since      1.0.0
 * @package    Learndash_Export_Import
 * @subpackage Learndash_Export_Import/includes
 * @author     WBCom Designs <admin@wbcomdesigns.com>
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Class LD_Data_Mapper
 */
class LD_Data_Mapper {

    /**
     * ID mapping array.
     *
     * @var array
     */
    private $id_mapping = array();

    /**
     * Set ID mapping.
     *
     * @param int $old_id Old ID.
     * @param int $new_id New ID.
     */
    public function set_mapping( $old_id, $new_id ) {
        $this->id_mapping[ $old_id ] = $new_id;
    }

    /**
     * Get mapped ID.
     *
     * @param int $old_id Old ID.
     * @return int New ID.
     */
    public function get_mapped_id( $old_id ) {
        return isset( $this->id_mapping[ $old_id ] ) ? $this->id_mapping[ $old_id ] : $old_id;
    }

    /**
     * Remap data recursively.
     *
     * @param mixed $data Data to remap.
     * @return mixed Remapped data.
     */
    public function remap_data( $data ) {
        if ( is_array( $data ) ) {
            foreach ( $data as $key => $value ) {
                $data[ $key ] = $this->remap_data( $value );
            }
        } elseif ( is_numeric( $data ) && isset( $this->id_mapping[ $data ] ) ) {
            $data = $this->id_mapping[ $data ];
        }

        return $data;
    }

    /**
     * Clear mapping.
     */
    public function clear_mapping() {
        $this->id_mapping = array();
    }
}