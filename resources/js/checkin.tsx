import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import { CheckInApp } from "./components/checkin/CheckInApp";

const container = document.getElementById("checkin-root");

if (container) {
    createRoot(container).render(
        <StrictMode>
            <CheckInApp />
        </StrictMode>,
    );
}
