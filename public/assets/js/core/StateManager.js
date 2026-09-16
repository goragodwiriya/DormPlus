export class StateManager {
    constructor(initialState = {}) {
        this.state = Object.freeze({...initialState});
        this.listeners = new Set();
    }

    get() {
        return this.state;
    }

    set(partialState) {
        this.state = Object.freeze({
            ...this.state,
            ...partialState
        });

        for (const listener of this.listeners) {
            listener(this.state);
        }
    }

    subscribe(listener) {
        this.listeners.add(listener);

        return () => {
            this.listeners.delete(listener);
        };
    }
}