<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schema.org JSON-LD for galleries (ArtGallery), exhibitions (ExhibitionEvent) and artists (Person).
 */
final class Artiey_AET_Schema {

	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'output' ), 30 );
	}

	private static function image_url( $post_id, $size = 'large' ) {
		$url = get_the_post_thumbnail_url( $post_id, $size );
		return $url ? $url : '';
	}

	private static function clean( $arr ) {
		foreach ( $arr as $k => $v ) {
			if ( is_array( $v ) ) {
				$v = self::clean( $v );
			}
			if ( '' === $v || null === $v || array() === $v ) {
				unset( $arr[ $k ] );
			} else {
				$arr[ $k ] = $v;
			}
		}
		return $arr;
	}

	private static function gallery_place( $gid ) {
		$g = Artiey_AET_Gallery::get( $gid );
		return array(
			'@type'   => 'Place',
			'name'    => $g['name'],
			'url'     => get_permalink( $gid ),
			'address' => array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => $g['address'],
				'addressLocality' => $g['city'],
				'addressCountry'  => 'IR',
			),
			'geo'     => ( '' !== $g['lat'] && '' !== $g['lng'] ) ? array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => (float) $g['lat'],
				'longitude' => (float) $g['lng'],
			) : array(),
		);
	}

	public static function output() {
		if ( ! is_singular( array( 'artiey_gallery', 'artiey_exhibition', 'artiey_artist', 'artiey_artwork' ) ) || ! Artiey_AET_Settings::get( 'schema' ) ) {
			return;
		}
		$id   = get_the_ID();
		$type = get_post_type( $id );
		$data = array();

		if ( 'artiey_gallery' === $type ) {
			$g     = Artiey_AET_Gallery::get( $id );
			$days  = array( 0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday' );
			$hours = array();
			foreach ( $g['hours'] as $w => $d ) {
				if ( $d['closed'] ) {
					continue;
				}
				foreach ( array( array( 'o1', 'c1' ), array( 'o2', 'c2' ) ) as $pair ) {
					if ( '' !== $d[ $pair[0] ] && '' !== $d[ $pair[1] ] ) {
						$hours[] = array(
							'@type'     => 'OpeningHoursSpecification',
							'dayOfWeek' => $days[ $w ],
							'opens'     => $d[ $pair[0] ],
							'closes'    => $d[ $pair[1] ],
						);
					}
				}
			}
			$same = array();
			if ( $g['instagram'] ) {
				$same[] = 'https://instagram.com/' . $g['instagram'];
			}
			if ( $g['telegram'] ) {
				$same[] = 'https://t.me/' . $g['telegram'];
			}
			if ( $g['website'] ) {
				$same[] = $g['website'];
			}
			$founding = '';
			if ( $g['founded'] ) {
				$gy       = Artiey_AET_Jalali::j2g( $g['founded'], 1, 1 );
				$founding = (string) $gy[0];
			}
			$phones = array_merge( $g['phones'], $g['mobiles'] );

			$data = array(
				'@context'                  => 'https://schema.org',
				'@type'                     => 'ArtGallery',
				'@id'                       => get_permalink( $id ) . '#gallery',
				'name'                      => $g['name'],
				'alternateName'             => $g['name_en'],
				'url'                       => get_permalink( $id ),
				'description'               => $g['short_bio'],
				'accessibilitySummary'      => $g['visitor_info'],
				'image'                     => $g['cover'] ? wp_get_attachment_url( $g['cover'] ) : self::image_url( $id ),
				'logo'                      => self::image_url( $id, 'medium' ),
				'telephone'                 => $phones ? Artiey_AET_Gallery::digits_only( $phones[0] ) : '',
				'email'                     => $g['email'],
				'foundingDate'              => $founding,
				'address'                   => array(
					'@type'           => 'PostalAddress',
					'streetAddress'   => $g['address'],
					'addressLocality' => $g['city'],
					'addressCountry'  => 'IR',
				),
				'geo'                       => ( '' !== $g['lat'] && '' !== $g['lng'] ) ? array(
					'@type'     => 'GeoCoordinates',
					'latitude'  => (float) $g['lat'],
					'longitude' => (float) $g['lng'],
				) : array(),
				'openingHoursSpecification' => $hours,
				'hasMap'                    => ( '' !== $g['lat'] && '' !== $g['lng'] ) ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( (float) $g['lat'] . ',' . (float) $g['lng'] ) : '',
				'additionalProperty'        => isset( array( 'physical' => 'گالری فیزیکی', 'online' => 'گالری آنلاین', 'hybrid' => 'گالری فیزیکی و آنلاین' )[ $g['venue_type'] ] ) ? array( '@type' => 'PropertyValue', 'name' => 'نوع گالری', 'value' => array( 'physical' => 'گالری فیزیکی', 'online' => 'گالری آنلاین', 'hybrid' => 'گالری فیزیکی و آنلاین' )[ $g['venue_type'] ] ) : array(),
				'sameAs'                    => $same,
			);
		} elseif ( 'artiey_exhibition' === $type ) {
			$start = get_post_meta( $id, '_ae_start', true );
			$end   = get_post_meta( $id, '_ae_end', true );
			if ( ! $start ) {
				return;
			}
			$gid    = (int) get_post_meta( $id, '_ae_gallery', true );
			$curator = (string) get_post_meta( $id, '_ae_curator', true );
			$admission = (string) get_post_meta( $id, '_ae_admission', true );
			$people = array();
			$arts   = get_post_meta( $id, '_ae_artists', true );
			if ( is_array( $arts ) ) {
				foreach ( $arts as $aid ) {
					$people[] = array( '@type' => 'Person', 'name' => get_the_title( (int) $aid ), 'url' => get_permalink( (int) $aid ) );
				}
			}
			$data = array(
				'@context'            => 'https://schema.org',
				'@type'               => 'ExhibitionEvent',
				'name'                => get_the_title( $id ),
				'url'                 => get_permalink( $id ),
				'description'         => get_the_excerpt( $id ),
				'image'               => self::image_url( $id ),
				'startDate'           => $start,
				'endDate'             => $end ? $end : $start,
				'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
				'location'            => $gid ? self::gallery_place( $gid ) : array(),
				'performer'           => $people,
				'organizer'           => $curator ? array( '@type' => 'Person', 'name' => $curator ) : array(),
				'additionalProperty'  => $admission ? array( '@type' => 'PropertyValue', 'name' => 'شرایط بازدید', 'value' => $admission ) : array(),
			);
		} elseif ( 'artiey_artwork' === $type ) {
			$a       = Artiey_AET_Artwork::get( $id );
			$creator = array();
			if ( $a['artist'] ) {
				$creator = array( '@type' => 'Person', 'name' => get_the_title( $a['artist'] ), 'url' => get_permalink( $a['artist'] ) );
			} elseif ( $a['artist_text'] ) {
				$creator = array( '@type' => 'Person', 'name' => $a['artist_text'] );
			}
			$offer = array();
			if ( $a['price'] > 0 && 'show' === $a['price_mode'] && in_array( $a['sale'], array( 'available', 'reserved' ), true ) ) {
				$toman = ( false !== Artiey_AET_Text::strpos( (string) Artiey_AET_Settings::get( 'currency' ), 'تومان' ) );
				$offer = array(
					'@type'         => 'Offer',
					'price'         => (string) ( $toman ? $a['price'] * 10 : $a['price'] ),
					'priceCurrency' => 'IRR',
					'availability'  => ( 'available' === $a['sale'] ) ? 'https://schema.org/InStock' : 'https://schema.org/LimitedAvailability',
					'url'           => get_permalink( $id ),
				);
			}
			$data = array(
				'@context'      => 'https://schema.org',
				'@type'         => 'VisualArtwork',
				'name'          => $a['title'],
				'alternateName' => $a['title_en'],
				'url'           => get_permalink( $id ),
				'image'         => self::image_url( $id ),
				'description'   => get_the_excerpt( $id ),
				'artMedium'     => $a['medium'],
				'artworkSurface' => $a['material'],
				'additionalProperty' => array_values( array_filter( array(
					$a['condition'] ? array( '@type' => 'PropertyValue', 'name' => 'وضعیت فیزیکی', 'value' => $a['condition'] ) : array(),
					$a['provenance'] ? array( '@type' => 'PropertyValue', 'name' => 'پیشینه / مالکیت', 'value' => $a['provenance'] ) : array(),
				) ) ),
				'creator'       => $creator,
				'offers'        => $offer,
			);
		} else { // Artist.
			$a    = Artiey_AET_Artist::get( $id );
			$same = array();
			if ( $a['instagram'] ) {
				$same[] = 'https://instagram.com/' . $a['instagram'];
			}
			if ( $a['telegram'] ) {
				$same[] = 'https://t.me/' . $a['telegram'];
			}
			if ( $a['website'] ) {
				$same[] = $a['website'];
			}
			$terms = get_the_terms( $id, 'artiey_style' );
			$data  = array(
				'@context'      => 'https://schema.org',
				'@type'         => 'Person',
				'name'          => $a['name'],
				'alternateName' => $a['name_en'],
				'url'           => get_permalink( $id ),
				'image'         => self::image_url( $id ),
				'description'   => implode( "\n\n", array_filter( array( $a['short_bio'], $a['statement'] ) ) ),
				'nationality'   => $a['nationality'] ? array( '@type' => 'Country', 'name' => $a['nationality'] ) : array(),
				'jobTitle'      => 'Artist',
				'homeLocation'  => $a['city'] ? array( '@type' => 'Place', 'name' => $a['city'] ) : array(),
				'knowsAbout'    => ( $terms && ! is_wp_error( $terms ) ) ? wp_list_pluck( $terms, 'name' ) : array(),
				'sameAs'        => $same,
			);
		}

		$data = self::clean( $data );
		if ( ! $data ) {
			return;
		}
		$json = wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( ! $json ) {
			return;
		}
		$json = str_replace( '</', '<\/', $json );
		echo "\n" . '<script type="application/ld+json">' . $json . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON, '</' escaped.
	}
}
