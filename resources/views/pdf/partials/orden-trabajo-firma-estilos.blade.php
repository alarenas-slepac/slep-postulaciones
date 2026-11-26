/* bloque firma/timbre/pie */
.firma-section {
    position: relative;
    width: 100%;
    margin-top: 34px;
    text-align: center;
}

/* área donde se montan timbre y firma */
.firma-layer {
    position: relative;
    width: 320px;
    height: 120px;
    margin: 0 auto -28px auto; /* hace que baje sobre el pie de firma */
}

/* timbre atrás */
.firma-layer .timbre-img {
    position: absolute;
    left: 50%;
    margin-left: -85px;
    top: 18px;
    width: 170px;
    height: auto;
    z-index: 1;
    opacity: 0.95;
}

/* firma encima del timbre */
.firma-layer .firma-img {
    position: absolute;
    left: 50%;
    margin-left: -140px;
    top: 0;
    width: 280px;
    height: auto;
    z-index: 2;
}

/* fallback cuando solo existe una imagen combinada */
.firma-combo {
    text-align: center;
    margin: 0 auto -18px auto;
}
.firma-combo .firma-img {
    width: 280px;
    height: auto;
}

.firma-text {
    position: relative;
    z-index: 0;
    text-align: center;
    font-size: 12px;
    line-height: 1.3;
    padding-top: 34px; /* espacio para que el montaje entre parcialmente sobre el pie */
}
