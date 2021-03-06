<?php
/**
 * Created by PhpStorm.
 * User: marlon
 * Date: 11/21/14
 * Time: 9:46 PM
 */

include_once 'includes/header.php';



?>


<!-- Page Content
    ================================================== -->

<!-- Title Header -->
<div class="row" ng-app="awesomeApp" ng-controller="ArtistListCtrl">
    <div class="span12">
        <h2>Musicians of Troy</h2>
        <p class="lead">Look up and support our brave musicians in their epic journey.</p>
    </div>

    <div class="span12">
        <form class="form-search">
            <div class="input-append">
                <input autofocus ng-model="query" type="text" placeholder="Search for an artist" class="span6 search-query">
                <button type="submit"  class="btn">Search</button>
            </div>
            </form>
    </div>


<!--    <li dir-paginate="meal in meals | filter:q | itemsPerPage: pageSize" current-page="currentPage">{{ meal }}</li>-->

    <div class="span4" dir-paginate="artist in artists | filter: query | itemsPerPage: pageSize" current-page="currentPage">
        <img src="{{artist.image_path}}"/>
        <h5>{{artist.artist_name}}</h5>
        <p>{{artist.biography}}</p>
        <a href="albums.php?artist_id={{artist.artist_id}}"><button class="btn btn-small btn-inverse" type="button">Read more</button></a>
        <br/><br/>
    </div>

    <div class="span12"><br/></div>
    <div class="span12">
    <div class="text-center">
        <dir-pagination-controls boundary-links="true" on-page-change="pageChangeHandler(newPageNumber)" template-url="dirPagination.tpl.html"></dir-pagination-controls>
    </div>
    </div>
    <div class="span12"><br/><br/></div>








</div>


<?php include_once 'includes/footer.php' ?>
